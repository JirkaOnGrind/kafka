"""Read-only SEO migration audit: public SQL fields, owner-authorized GET crawl.

Never executes source PHP, reads customer tables, submits forms, or follows cart links.
Production requests are serial and throttled; redirects stay on the two allowed hosts.
"""
import argparse
import collections
import concurrent.futures
import csv
import datetime
import hashlib
import html
import http.client
from html.parser import HTMLParser
import json
from pathlib import Path
import re
import sys
import socket
import time
import threading
import urllib.error
import urllib.parse
import urllib.request
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / 'migration'))
from audit_dump import tuples

SOURCE = 'https://kafka.monster'
TARGET = 'http://localhost:8083'
AGENT = 'KafkaMigrationAudit/1.0 (owner-authorized read-only SEO verification)'
FIELDS = {
    'lang': ['id_lang', 'iso_code', 'active'],
    'configuration': ['name', 'value'],
    'product': ['id_product', 'id_category_default', 'active', 'ean13'],
    'product_lang': ['id_product', 'id_lang', 'id_shop', 'name', 'link_rewrite', 'meta_title', 'meta_description'],
    'category': ['id_category', 'active', 'id_parent'],
    'category_lang': ['id_category', 'id_lang', 'id_shop', 'name', 'link_rewrite', 'meta_title', 'meta_description'],
    'cms': ['id_cms', 'active'],
    'cms_lang': ['id_cms', 'id_lang', 'id_shop', 'meta_title', 'link_rewrite', 'meta_description'],
    'cms_category': ['id_cms_category', 'active'],
    'cms_category_lang': ['id_cms_category', 'id_lang', 'id_shop', 'name', 'link_rewrite', 'meta_title', 'meta_description'],
    'manufacturer': ['id_manufacturer', 'active', 'name'],
    'manufacturer_lang': ['id_manufacturer', 'id_lang', 'meta_title', 'meta_description'],
    'supplier': ['id_supplier', 'active', 'name'],
    'supplier_lang': ['id_supplier', 'id_lang', 'meta_title', 'meta_description'],
    'meta': ['id_meta', 'page'],
    'meta_lang': ['id_meta', 'id_lang', 'id_shop', 'title', 'description', 'url_rewrite'],
}
SAFE_CONFIG = {'PS_REWRITING_SETTINGS', 'PS_LANG_DEFAULT', 'PS_CANONICAL_REDIRECT', 'PS_SHOP_NAME',
               'PS_ROUTE_product_rule', 'PS_ROUTE_category_rule', 'PS_ROUTE_cms_rule',
               'PS_ROUTE_cms_category_rule', 'PS_ROUTE_manufacturer_rule', 'PS_ROUTE_supplier_rule'}


def save(path, obj):
    path.write_text(json.dumps(obj, ensure_ascii=False, indent=2), encoding='utf-8')


def write_csv(path, rows):
    if not rows:
        path.write_text('', encoding='utf-8-sig')
        return
    fields = list(dict.fromkeys(k for row in rows for k in row))
    with path.open('w', encoding='utf-8-sig', newline='') as stream:
        writer = csv.DictWriter(stream, fields)
        writer.writeheader()
        for row in rows:
            writer.writerow({k: json.dumps(v, ensure_ascii=False) if isinstance(v, (dict, list)) else v for k, v in row.items()})


def source_catalog(dump):
    data = collections.defaultdict(list)
    active, parts = None, []
    with dump.open(encoding='utf-8-sig') as stream:
        for line in stream:
            if line.startswith('INSERT INTO'):
                match = re.match(r'INSERT INTO `([^`]+)`\s*\((.*?)\) VALUES\s*(.*)', line, re.S)
                if match:
                    table = match[1].split('_', 1)[-1]
                    if table in FIELDS:
                        active = (table, re.findall(r'`([^`]+)`', match[2]))
                        parts = [match[3]]
            elif active:
                parts.append(line)
            if active and line.rstrip().endswith(';'):
                table, columns = active
                selected = [(k, columns.index(k)) for k in FIELDS[table] if k in columns]
                for values in tuples(''.join(parts)):
                    row = {key: values[i] for key, i in selected}
                    if table != 'configuration' or row['name'] in SAFE_CONFIG:
                        data[table].append(row)
                active, parts = None, []
    langs = {r['id_lang']: r for r in data['lang']}
    categories = {(r['id_category'], r['id_lang']): r for r in data['category_lang'] if r.get('id_shop', 1) == 1}
    rows = []
    for entity in ['product', 'category', 'cms', 'cms_category', 'manufacturer', 'supplier']:
        pk = 'id_' + entity
        base = {r[pk]: r for r in data[entity]}
        for language_row in data[entity + '_lang']:
            if language_row.get('id_shop', 1) != 1 or language_row[pk] not in base:
                continue
            row = {**base[language_row[pk]], **language_row}
            iso = langs[row['id_lang']]['iso_code']
            slug = row.get('link_rewrite') or ''
            if entity == 'product':
                cat = categories.get((row['id_category_default'], row['id_lang']), {}).get('link_rewrite', '')
                row['category_slug'] = cat
                suffix = '-' + str(row['ean13']) if row.get('ean13') else ''
                path = (cat + '/' if cat else '') + str(row[pk]) + '-' + slug + suffix + '.html'
            elif entity == 'category': path = str(row[pk]) + '-' + slug
            elif entity == 'cms':
                path = 'content/' + str(row[pk]) + '-' + slug
                row['name'] = row['meta_title']
            elif entity == 'cms_category': path = 'content/category/' + str(row[pk]) + '-' + slug
            else:
                # Source Tools::link_rewrite equivalent for the observed ASCII brand names.
                import unicodedata
                brand = unicodedata.normalize('NFKD', row['name']).encode('ascii', 'ignore').decode().lower()
                brand = re.sub(r'[^a-z0-9]+', '-', brand).strip('-')
                path = str(row[pk]) + ('_' if entity == 'manufacturer' else '__') + brand
            row.update(entity=entity, id=row[pk], language=iso, url=SOURCE + '/' + iso + '/' + path)
            rows.append(row)
    pages = {r['id_meta']: r['page'] for r in data['meta']}
    for row in data['meta_lang']:
        if row.get('id_shop', 1) != 1 or row['id_meta'] not in pages: continue
        page = pages[row['id_meta']]
        if page.startswith('module-'): continue
        iso = langs[row['id_lang']]['iso_code']
        path = '' if page == 'index' else row['url_rewrite']
        url = SOURCE + '/' + iso + '/' + path if path or page == 'index' else SOURCE + '/index.php?controller=' + page
        rows.append({**row, 'entity': 'system', 'id': page, 'language': iso, 'url': url, 'active': 1})
    return {'languages': list(langs.values()), 'configuration': {r['name']: r['value'] for r in data['configuration']}, 'rows': rows,
            'source_sha256': hashlib.file_digest(dump.open('rb'), 'sha256').hexdigest()}


def path(url):
    p = urllib.parse.urlsplit(url)
    return urllib.parse.unquote(p.path) + ('?' + p.query if p.query else '')


def identify(url):
    value = urllib.parse.unquote(urllib.parse.urlsplit(url).path)
    match = re.match(r'^/(cs|sk|de|en)/(.*)$', value)
    iso, value = (match[1], match[2]) if match else ('cs', value.lstrip('/'))
    patterns = [('product', r'(?:[^/]+/)?(\d+)-[^/]+\.html'),
                ('cms', r'content/(\d+)-[^/]+'), ('cms_category', r'content/category/(\d+)-[^/]+'),
                ('category', r'(\d+)-[^/]+'), ('supplier', r'(\d+)__[^/]+'), ('manufacturer', r'(\d+)_[^/]+')]
    for entity, pattern in patterns:
        found = re.fullmatch(pattern, value)
        if found: return entity, str(int(found[1])), iso
    return None


def compare(source, target, output):
    targets = {(r['entity'], str(r['id']), r['language']): r for r in target['rows']}
    rows, diffs = [], []
    active_langs = {r['iso_code'] for r in source['languages'] if int(r['active'])}
    for old in source['rows']:
        new = targets.get((old['entity'], str(old['id']), old['language']))
        public = old['language'] in active_langs and int(old.get('active', 0)) == 1
        row = {'entity': old['entity'], 'id': old['id'], 'language': old['language'], 'source_active': int(old.get('active', 0)),
               'active_language': old['language'] in active_langs, 'public_candidate': public,
               'source_url': old['url'], 'target_url': new['url'] if new else '', 'exists': new is not None,
               'path_equal': new is not None and path(old['url']) == path(new['url']),
               'target_active': new.get('active') if new else '', 'changed_fields': []}
        if new:
            for key in ['name', 'link_rewrite', 'meta_title', 'meta_description', 'id_category_default']:
                if key in old and key in new and str(old[key] or '') != str(new[key] or ''):
                    row['changed_fields'].append(key)
                    diffs.append({'entity': old['entity'], 'id': old['id'], 'language': old['language'], 'field': key, 'source': old[key], 'target': new[key]})
        rows.append(row)
    write_csv(output / 'catalog-url-comparison.csv', rows)
    write_csv(output / 'catalog-metadata-differences.csv', diffs)
    save(output / 'catalog-comparison.json', {'counts': dict(collections.Counter(r['entity'] for r in rows)),
         'total': len(rows), 'missing': sum(not r['exists'] for r in rows), 'equal_paths': sum(r['path_equal'] for r in rows),
         'changed_paths': sum(r['exists'] and not r['path_equal'] for r in rows), 'metadata_differences': len(diffs),
         'active_languages': sorted(active_langs), 'by_entity_language': [
             {'entity': entity, 'language': iso, 'total': len(group), 'path_equal': sum(r['path_equal'] for r in group),
              'missing': sum(not r['exists'] for r in group), 'metadata_changed_rows': sum(bool(r['changed_fields']) for r in group)}
             for (entity, iso), group in group_rows(rows).items()]})
    return rows


def group_rows(rows):
    result = collections.defaultdict(list)
    for row in rows: result[(row['entity'], row['language'])].append(row)
    return result


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl): return None


class LocalHTTPConnection(http.client.HTTPConnection):
    def connect(self):
        # Docker publishes IPv4 only; preserve the requested URL and Host header.
        if self.host == 'localhost' and self.port == 8083:
            self.sock = socket.create_connection(('127.0.0.1', 8083), self.timeout, self.source_address)
        else:
            super().connect()


class LocalHTTPHandler(urllib.request.HTTPHandler):
    def http_open(self, request): return self.do_open(LocalHTTPConnection, request)


class Fetcher:
    def __init__(self):
        self.opener = urllib.request.build_opener(NoRedirect(), LocalHTTPHandler())
        self.last_source = 0
        self.robots = []
        self.backoff_until = 0

    def allowed(self, url):
        parsed = urllib.parse.urlsplit(url)
        if parsed.netloc not in ['kafka.monster', 'www.kafka.monster', 'localhost:8083']: return False
        if parsed.scheme not in ['https', 'http']: return False
        if parsed.query and not all(k in ['p', 'page', 'controller', 'id_lang', 'id_product', 'id_category', 'id_cms'] for k, _ in urllib.parse.parse_qsl(parsed.query)): return False
        if parsed.netloc.endswith('kafka.monster'):
            candidate = parsed.path + ('?' + parsed.query if parsed.query else '')
            matches = [(len(rule), allow) for rule, allow in self.robots if re.match('^' + re.escape(rule).replace(r'\*', '.*').replace(r'\$', '$'), candidate)]
            if matches and not max(matches)[1]: return False
        return True

    def fetch(self, url, method='GET'):
        initial, chain = url, []
        for _ in range(8):
            if not self.allowed(url):
                return {'url': initial, 'final_url': url, 'status': 0, 'error': 'blocked_by_audit_scope_or_robots', 'chain': chain}, ''
            production = urllib.parse.urlsplit(url).netloc.endswith('kafka.monster')
            if production:
                time.sleep(max(0, self.last_source + 0.65 - time.monotonic(), self.backoff_until - time.monotonic()))
                self.last_source = time.monotonic()
            started = time.monotonic()
            try:
                response = self.opener.open(urllib.request.Request(url, method=method, headers={'User-Agent': AGENT, 'Accept': 'text/html,application/xml,text/plain;q=0.9'}), timeout=25)
            except urllib.error.HTTPError as error: response = error
            except Exception as error:
                return {'url': initial, 'final_url': url, 'status': 0, 'error': type(error).__name__ + ': ' + str(error), 'chain': chain}, ''
            with response:
                status = response.code
                headers = response.headers
                chain.append({'url': url, 'status': status, 'location': headers.get('Location', '')})
                if status in [301, 302, 303, 307, 308] and headers.get('Location'):
                    url = urllib.parse.urljoin(url, headers['Location'])
                    continue
                raw = response.read(5_000_001)
                body = raw[:5_000_000].decode(headers.get_content_charset() or 'utf-8', 'replace')
                result = {'url': initial, 'final_url': url, 'status': status, 'chain': chain, 'bytes': len(raw),
                          'truncated': len(raw) > 5_000_000, 'content_type': headers.get('Content-Type', ''),
                          'x_robots_tag': headers.get('X-Robots-Tag', ''), 'seconds': round(time.monotonic() - started, 3)}
                if production and status in [429, 503]: self.backoff_until = time.monotonic() + 30
                return result, body
        return {'url': initial, 'final_url': url, 'status': 0, 'error': 'redirect_loop', 'chain': chain}, ''


class Page(HTMLParser):
    def __init__(self, base):
        super().__init__(convert_charrefs=True)
        self.base = base
        self.title = []; self.h1 = []; self.in_title = False; self.in_h1 = 0
        self.meta = {}; self.canonical = []; self.hreflang = {}; self.links = set(); self.images = []; self.jsonld = 0; self.body_id = ''; self.microdata = []

    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        if a.get('itemtype'): self.microdata.append(a['itemtype'])
        if tag == 'title': self.in_title = True
        if tag == 'h1': self.in_h1 += 1; self.h1.append('')
        if tag == 'body': self.body_id = a.get('id', '')
        if tag == 'meta': self.meta[(a.get('name') or a.get('property') or '').lower()] = a.get('content', '')
        if tag == 'link':
            rel = (a.get('rel') or '').lower().split()
            if 'canonical' in rel: self.canonical.append(urllib.parse.urljoin(self.base, a.get('href', '')))
            if 'alternate' in rel and a.get('hreflang'): self.hreflang[a['hreflang']] = urllib.parse.urljoin(self.base, a.get('href', ''))
        if tag == 'a' and a.get('href'): self.links.add(urllib.parse.urljoin(self.base, a['href']))
        if tag == 'img': self.images.append({'src': urllib.parse.urljoin(self.base, a.get('src', '')), 'alt': a.get('alt')})
        if tag == 'script' and a.get('type') == 'application/ld+json': self.jsonld += 1

    def handle_endtag(self, tag):
        if tag == 'title': self.in_title = False
        if tag == 'h1': self.in_h1 = max(0, self.in_h1 - 1)

    def handle_data(self, data):
        if self.in_title: self.title.append(data)
        if self.in_h1 and self.h1: self.h1[-1] += data

    def result(self):
        clean = lambda s: re.sub(r'\s+', ' ', s).strip()
        return {'title': clean(''.join(self.title)), 'description': self.meta.get('description', ''), 'robots': self.meta.get('robots', ''),
                'canonical': self.canonical, 'hreflang': self.hreflang, 'h1': [clean(s) for s in self.h1],
                'og_title': self.meta.get('og:title', ''), 'og_url': self.meta.get('og:url', ''), 'jsonld_blocks': self.jsonld,
                'microdata_types': sorted(set(self.microdata)),
                'body_id': self.body_id, 'internal_links': sorted(u for u in self.links if urllib.parse.urlsplit(u).netloc in ['kafka.monster', 'localhost:8083']),
                'image_count': len(self.images), 'images_missing_alt': sum(i['alt'] is None for i in self.images)}


def public_path(url):
    parsed = urllib.parse.urlsplit(url)
    if parsed.query or parsed.fragment: return False
    return bool(re.fullmatch(r'/(?:cs/|sk/|de/|en/)?(?:|[\w-]+/\d+-[^/]+\.html|\d+-[^/]+|content/\d+-[^/]+|content/category/\d+-[^/]+|new-products|best-sales|prices-drop|contact-us|contact|stores|manufacturers|suppliers|sitemap)', parsed.path))


def discover(fetcher, output):
    robots_result, robots = fetcher.fetch(SOURCE + '/robots.txt')
    (output / 'source-robots.txt').write_text(robots, encoding='utf-8')
    # The public file has one User-agent group (*); rules are enforced during all following crawls.
    for line in robots.splitlines():
        if ':' in line and line.split(':', 1)[0].lower() in ['allow', 'disallow']:
            key, value = line.split(':', 1)
            if value.strip(): fetcher.robots.append((value.strip(), key.lower() == 'allow'))
    sitemap_queue = [SOURCE + '/1_index_sitemap.xml']
    for line in robots.splitlines():
        if line.lower().startswith('sitemap:'): sitemap_queue.append(line.split(':', 1)[1].strip())
    seen, locations, sitemap_results = set(), set(), []
    while sitemap_queue and len(seen) < 20:
        url = sitemap_queue.pop(0)
        if url in seen: continue
        seen.add(url)
        result, body = fetcher.fetch(url)
        sitemap_results.append(result)
        if result['status'] != 200: continue
        try:
            root = ET.fromstring(body)
            locs = [el.text.strip() for el in root.findall('./{*}sitemap/{*}loc') if el.text]
            if locs: sitemap_queue.extend(locs)
            else: locations.update(el.text.strip() for el in root.findall('./{*}url/{*}loc') if el.text)
        except ET.ParseError as error: result['xml_error'] = str(error)
    pages, links = [], set()
    for suffix in ['/', '/cs/', '/sk/', '/de/']:
        result, body = fetcher.fetch(SOURCE + suffix)
        parser = Page(result['final_url']); parser.feed(body)
        result.update(parser.result()); pages.append(result)
        links.update(u for u in parser.links if public_path(u))
    save(output / 'discovery.json', {'robots': robots_result, 'sitemaps': sitemap_results, 'sitemap_url_count': len(locations), 'homepages': pages})
    write_csv(output / 'live-sitemap-urls.csv', [{'url': u} for u in sorted(locations)])
    write_csv(output / 'live-homepage-links.csv', [{'url': u} for u in sorted(links)])
    return locations, links


def plan_crawl(source, comparison, locations, links, output):
    candidates = {'https://kafka.monster/', 'https://kafka.monster/cs/', 'https://kafka.monster/sk/', 'https://kafka.monster/de/'}
    candidates.update(u for u in links if public_path(u))
    active = [r for r in comparison if r['public_candidate']]
    candidates.update(r['source_url'] for r in active if r['entity'] in ['category', 'cms', 'cms_category', 'manufacturer', 'supplier'] and str(r['id']) not in ['1', '2'])
    products = [r for r in active if r['entity'] == 'product']
    for iso, limit in [('cs', 130), ('sk', 20), ('de', 20)]:
        group = sorted([r for r in products if r['language'] == iso], key=lambda r: hashlib.sha256(r['source_url'].encode()).hexdigest())
        candidates.update(r['source_url'] for r in group[:limit])
        # Add the latest items as well as a reproducible random sample.
        candidates.update(r['source_url'] for r in sorted(group, key=lambda r: int(r['id']), reverse=True)[:10])
    known = {path(r['source_url']) for r in comparison}
    extras = sorted(u for u in locations if path(u) not in known and public_path(u))
    candidates.update(extras[:70])
    candidates.update(u for u in locations if public_path(u) and '/content/' in u)
    targets = {path(r['source_url']): r['target_url'] for r in comparison}
    by_id = {(r['entity'], str(r['id']), r['language']): r['target_url'] for r in comparison}
    candidates.update(u for u in locations if public_path(u) and path(u) not in known and identify(u) not in by_id)
    plan = [{'source_url': u, 'legacy_target_url': TARGET + path(u), 'native_target_url': targets.get(path(u), by_id.get(identify(u), ''))} for u in sorted(candidates) if public_path(u)]
    probes = []
    for iso, limit in [('cs', 10), ('sk', 3), ('de', 3)]:
        inactive = sorted([r for r in source['rows'] if r['entity'] == 'product' and r['language'] == iso and not int(r['active'])], key=lambda r: int(r['id']))[:limit]
        for row in inactive:
            probes.append({'source_url': row['url'], 'legacy_target_url': TARGET + path(row['url']),
                           'native_target_url': by_id[('product', str(row['id']), iso)], 'probe': 'inactive_product'})
    category_counts = collections.Counter(r['id_category_default'] for r in source['rows'] if r['entity'] == 'product' and int(r['active']) and r['language'] == 'cs')
    rows_by_id = {(r['entity'], str(r['id']), r['language']): r for r in comparison}
    for cat_id, _ in category_counts.most_common(3):
        for iso in ['cs', 'sk']:
            row = rows_by_id[('category', str(cat_id), iso)]
            probes.append({'source_url': row['source_url'] + '?p=2', 'legacy_target_url': TARGET + path(row['source_url']) + '?p=2',
                           'native_target_url': row['target_url'] + '?page=2', 'probe': 'legacy_pagination'})
    existing = {r['source_url'] for r in plan}
    plan.extend(r for r in probes if r['source_url'] not in existing)
    live_new_products = output / 'new-products-discovery.json'
    if live_new_products.exists():
        existing = {r['source_url'] for r in plan}
        plan.extend(r for r in json.loads(live_new_products.read_text(encoding='utf-8')).get('added_probes', []) if r['source_url'] not in existing)
    save(output / 'crawl-plan.json', plan)
    return plan


def crawl(fetcher, plan, output, side):
    cache_file = output / (side + '-http.jsonl')
    done = set()
    if cache_file.exists():
        done = {json.loads(line)['url'] for line in cache_file.read_text(encoding='utf-8').splitlines() if line.strip()}
    urls = []
    for row in plan:
        urls += [row['source_url']] if side == 'source' else [row['legacy_target_url'], row['native_target_url']]
    urls = list(dict.fromkeys(u for u in urls if u))
    def read_page(url, client):
        result, body = client.fetch(url)
        parser = Page(result.get('final_url', url))
        try: parser.feed(body); result.update(parser.result())
        except Exception as error: result['parse_error'] = str(error)
        return result

    if side == 'target':
        # At most two local requests; the production worker remains strictly serial.
        worker_state = threading.local()
        def read_local(url):
            if not hasattr(worker_state, 'client'): worker_state.client = Fetcher()
            return read_page(url, worker_state.client)
        with cache_file.open('a', encoding='utf-8') as stream, concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
            pending = {pool.submit(read_local, url): url for url in urls if url not in done}
            count = len(done)
            for future in concurrent.futures.as_completed(pending):
                result = future.result()
                stream.write(json.dumps(result, ensure_ascii=False) + '\n'); stream.flush()
                count += 1
                if count % 20 == 0: print(json.dumps({'side': side, 'completed': count, 'total': len(urls), 'status': result['status']}), flush=True)
        print(json.dumps({'side': side, 'recorded': count, 'planned': len(urls)}), flush=True)
        return
    with cache_file.open('a', encoding='utf-8') as stream:
        for i, url in enumerate(urls, 1):
            if url in done: continue
            result = read_page(url, fetcher)
            stream.write(json.dumps(result, ensure_ascii=False) + '\n'); stream.flush()
            if i % 20 == 0: print(json.dumps({'side': side, 'completed': i, 'total': len(urls), 'status': result['status']}), flush=True)
            if side == 'source' and result['status'] in [429, 503]:
                print('Production throttling observed; stopping source crawl for later resumption.', flush=True)
                break
    print(json.dumps({'side': side, 'recorded': len(cache_file.read_text(encoding='utf-8').splitlines()), 'planned': len(urls)}), flush=True)


def report(output):
    source = json.loads((output / 'source-public-catalog.json').read_text(encoding='utf-8'))
    target = json.loads((output / 'target-public-catalog.json').read_text(encoding='utf-8'))
    comparison = json.loads((output / 'comparison-rows.json').read_text(encoding='utf-8'))
    by_path = {path(r['source_url']): r for r in comparison}
    by_id = {(r['entity'], str(r['id']), r['language']): r for r in comparison}
    sitemap_urls = [r['url'] for r in csv.DictReader((output / 'live-sitemap-urls.csv').open(encoding='utf-8-sig'))]
    sitemap_rows = []
    for url in sitemap_urls:
        match = by_path.get(path(url)) or by_id.get(identify(url))
        sitemap_rows.append({'source_url': url, 'exact_snapshot_path': path(url) in by_path,
            'entity_found_by_id': match is not None, 'entity': match['entity'] if match else '',
            'id': match['id'] if match else '', 'language': match['language'] if match else '',
            'snapshot_active': match['source_active'] if match else '', 'current_source_snapshot_url': match['source_url'] if match else '',
            'target_current_local_url': match['target_url'] if match else '',
            'target_same_path': bool(match and path(url) == path(match['target_url']))})
    write_csv(output / 'sitemap-migration-comparison.csv', sitemap_rows)
    http = {}
    for side in ['source', 'target']:
        file = output / (side + '-http.jsonl')
        http[side] = {r['url']: r for r in [json.loads(line) for line in file.read_text(encoding='utf-8').splitlines()]} if file.exists() else {}
    pairs = []
    for item in json.loads((output / 'crawl-plan.json').read_text(encoding='utf-8')):
        old = http['source'].get(item['source_url'])
        new = http['target'].get(item['legacy_target_url'])
        native = http['target'].get(item['native_target_url'])
        status = 'not_tested'
        if old and new:
            if old['status'] == 200 and new['status'] == 404: status = 'MIGRATION_REGRESSION_404'
            elif old['status'] == 200 and new['status'] == 200:
                status = 'redirect_302' if any(h['status'] == 302 for h in new['chain']) else ('redirect_301' if len(new['chain']) > 1 else 'OK_200')
            elif old['status'] == 404: status = 'ALREADY_404_ON_SOURCE'
            else: status = 'review_other_status'
        entity_key = identify(item['source_url'])
        cause = ''
        if status == 'MIGRATION_REGRESSION_404':
            cause = 'live_entity_not_in_snapshot' if entity_key and entity_key not in by_id else 'existing_entity_legacy_route_broken'
        normalize = lambda text: re.sub(r'\s+', ' ', html.unescape(text or '')).strip()
        old_description = old.get('description', '') if old else ''
        new_description = (native or new or {}).get('description', '')
        pairs.append({**item, 'classification': status, 'regression_cause': cause, 'source_status': old['status'] if old else '', 'legacy_target_status': new['status'] if new else '',
            'native_target_status': native['status'] if native else '', 'source_final': old.get('final_url', '') if old else '',
            'target_final': new.get('final_url', '') if new else '', 'source_title': old.get('title', '') if old else '',
            'target_title': (native or new or {}).get('title', ''), 'source_description': old.get('description', '') if old else '',
            'target_description': (native or new or {}).get('description', ''), 'target_canonical': (native or new or {}).get('canonical', []),
            'description_equal_after_whitespace_normalization': normalize(old_description) == normalize(new_description),
            'target_hreflang': (native or new or {}).get('hreflang', {}), 'source_h1': old.get('h1', []) if old else [],
            'target_h1': (native or new or {}).get('h1', []), 'source_chain': old.get('chain', []) if old else [], 'target_chain': new.get('chain', []) if new else []})
    write_csv(output / 'http-page-comparison.csv', pairs)
    regressions = [r for r in pairs if r['classification'] == 'MIGRATION_REGRESSION_404']
    write_csv(output / 'migration-regressions-404.csv', regressions)
    new_live = [r for r in pairs if r['source_status'] == 200 and (identify(r['source_url']) is not None and identify(r['source_url']) not in by_id)]
    write_csv(output / 'live-entities-outside-snapshot.csv', new_live)
    live_entity_keys = {identify(r['source_url'])[:2] for r in new_live}
    source_rows = list(http['source'].values()); target_rows = list(http['target'].values())
    ok_native = [r for r in target_rows if r['status'] == 200 and r.get('body_id') != 'pagenotfound']
    external_canonical = [r['url'] for r in ok_native if any(urllib.parse.urlsplit(u).netloc != 'localhost:8083' for u in r.get('canonical', []))]
    canonical_bad = [r['url'] for r in ok_native if len(r.get('canonical', [])) != 1]
    source_ok = [r for r in source_rows if r['status'] == 200]
    paired_native = [r for r in pairs if r['source_status'] == 200 and r['native_target_status'] == 200]
    title_diffs = [r for r in paired_native if r['source_title'] != r['target_title']]
    description_diffs = [r for r in paired_native if not r['description_equal_after_whitespace_normalization']]
    write_csv(output / 'rendered-title-differences.csv', title_diffs)
    write_csv(output / 'rendered-description-differences.csv', description_diffs)
    unresolved_sitemap = [r for r in sitemap_rows if not r['entity_found_by_id']]
    for row in unresolved_sitemap:
        checked = http['source'].get(row['source_url'], {})
        row['live_source_status'] = checked.get('status', '')
        row['live_source_final_url'] = checked.get('final_url', '')
    write_csv(output / 'sitemap-entities-not-in-snapshot.csv', unresolved_sitemap)
    sitemap_index = output / '1_index_sitemap.xml'
    lastmods = [el.text for el in ET.fromstring(sitemap_index.read_text(encoding='utf-8')).findall('./{*}sitemap/{*}lastmod')] if sitemap_index.exists() else []
    public_comparison = [r for r in comparison if r['public_candidate'] and r['entity'] != 'system']
    summary = {'generated_at': datetime.datetime.now(datetime.timezone.utc).isoformat(),
        'source_snapshot_sha256': source['source_sha256'], 'catalog_rows': len(comparison),
        'catalog_missing': sum(not r['exists'] for r in comparison),
        'catalog_metadata_changed_rows': sum(bool(r['changed_fields']) for r in comparison),
        'active_catalog_rows': len(public_comparison), 'active_catalog_changed_paths': sum(not r['path_equal'] for r in public_comparison),
        'sitemap_urls': len(sitemap_urls), 'sitemap_exact_snapshot_paths': sum(r['exact_snapshot_path'] for r in sitemap_rows),
        'sitemap_entities_matched': sum(r['entity_found_by_id'] for r in sitemap_rows),
        'sitemap_same_target_paths': sum(r['target_same_path'] for r in sitemap_rows),
        'http_source_count': len(source_rows), 'http_target_count': len(target_rows),
        'http_source_statuses': dict(collections.Counter(r['status'] for r in source_rows)),
        'http_target_statuses': dict(collections.Counter(r['status'] for r in target_rows)),
        'http_pair_classification': dict(collections.Counter(r['classification'] for r in pairs)),
        'regression_causes': dict(collections.Counter(r['regression_cause'] for r in regressions)),
        'live_entities_missing_from_snapshot': dict(collections.Counter(entity for entity, _ in live_entity_keys)),
        'target_successes': len(ok_native), 'target_canonical_missing_or_duplicate': canonical_bad,
        'target_canonical_wrong_host': external_canonical,
        'target_hreflang_present': sum(bool(r.get('hreflang')) for r in ok_native),
        'target_jsonld_present': sum(bool(r.get('jsonld_blocks')) for r in ok_native),
        'target_noindex_header': sum('noindex' in r.get('x_robots_tag', '').lower() for r in target_rows),
        'source_canonical_present': sum(bool(r.get('canonical')) for r in source_ok),
        'source_hreflang_present': sum(bool(r.get('hreflang')) for r in source_ok),
        'source_jsonld_present': sum(bool(r.get('jsonld_blocks')) for r in source_ok),
        'source_sitemap_index_lastmod': sorted(set(lastmods)),
        'rendered_native_pairs': len(paired_native), 'rendered_title_differences': len(title_diffs),
        'rendered_description_differences_normalized': len(description_diffs),
        'unresolved_sitemap_source_statuses': dict(collections.Counter(r['live_source_status'] for r in unresolved_sitemap)),
        'target_configuration': target['configuration'], 'complete_http_pairs': sum(r['classification'] != 'not_tested' for r in pairs),
        'planned_http_pairs': len(pairs)}
    save(output / 'summary.json', summary)
    print(json.dumps(summary, ensure_ascii=True, indent=2)[:6500])


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('stage', choices=['prepare', 'discover', 'source', 'target', 'report'])
    ap.add_argument('--output', type=Path, default=ROOT / 'migration/evidence/seo-20261019')
    args = ap.parse_args(); output = args.output; output.mkdir(parents=True, exist_ok=True)
    if args.stage == 'prepare':
        source = source_catalog(ROOT / 'local/source/20261014-135846/database.sql')
        target = json.loads((ROOT / 'local/private/migration-lab-20261018/seo-target-catalog-20261019.json').read_text(encoding='utf-8-sig'))
        save(output / 'source-public-catalog.json', source); save(output / 'target-public-catalog.json', target)
        comparison = compare(source, target, output)
        save(output / 'comparison-rows.json', comparison)
        print((output / 'catalog-comparison.json').read_text(encoding='utf-8')[:1800])
    elif args.stage == 'discover':
        fetcher = Fetcher(); locations, links = discover(fetcher, output)
        source = json.loads((output / 'source-public-catalog.json').read_text(encoding='utf-8'))
        comparison = json.loads((output / 'comparison-rows.json').read_text(encoding='utf-8'))
        plan = plan_crawl(source, comparison, locations, links, output)
        known = {path(r['source_url']): r for r in comparison}
        write_csv(output / 'sitemap-migration-comparison.csv', [
            {'source_url': u, 'in_snapshot': path(u) in known, 'entity': known.get(path(u), {}).get('entity', ''),
             'target_url': known.get(path(u), {}).get('target_url', ''), 'path_equal': known.get(path(u), {}).get('path_equal', '')}
            for u in sorted(locations)])
        print(json.dumps({'sitemap_urls': len(locations), 'homepage_public_links': len(links), 'crawl_pairs': len(plan)}))
    elif args.stage == 'report':
        report(output)
    else:
        fetcher = Fetcher()
        for line in (output / 'source-robots.txt').read_text(encoding='utf-8').splitlines():
            if ':' in line:
                key, value = line.split(':', 1)
                if key.lower() in ['allow', 'disallow'] and value.strip(): fetcher.robots.append((value.strip(), key.lower() == 'allow'))
        plan = json.loads((output / 'crawl-plan.json').read_text(encoding='utf-8'))
        crawl(fetcher, plan, output, args.stage)


if __name__ == '__main__': main()
