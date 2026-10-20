"""Fresh local-only acceptance audit. Baseline source HTTP is historical evidence, never current proof.

No SQL import, production access, resume cache, customer data or storefront writes.
"""
import argparse
import concurrent.futures
import csv
import datetime as dt
import hashlib
import json
from pathlib import Path
import re
import threading
import urllib.error
import urllib.parse as U
import urllib.request
import xml.etree.ElementTree as ET

import importlib.util

ROOT = Path(__file__).resolve().parents[1]
BASELINE = ROOT / 'migration/evidence/seo-20261019'
HOST = 'http://localhost:8083'
spec = importlib.util.spec_from_file_location('baseline_auditor', ROOT / 'scripts/audit-seo-migration.py')
old = importlib.util.module_from_spec(spec)
spec.loader.exec_module(old)


def read(path):
    return json.loads(Path(path).read_text(encoding='utf-8-sig'))


def save(path, value):
    Path(path).write_text(json.dumps(value, ensure_ascii=False, indent=2), encoding='utf-8')


def key(row):
    return row['entity'], str(row['id']), row['language']


def local(url):
    p = U.urlsplit(url)
    return HOST + p.path + ('?' + p.query if p.query else '')


class Client:
    def __init__(self):
        self.opener = urllib.request.build_opener(urllib.request.ProxyHandler({}), old.NoRedirect(), old.LocalHTTPHandler())

    def fetch(self, url):
        initial, chain, seen = url, [], set()
        for _ in range(6):
            if U.urlsplit(url).netloc != 'localhost:8083' or U.urlsplit(url).scheme != 'http':
                return {'url': initial, 'status': 0, 'error': 'external_redirect_blocked', 'chain': chain}, ''
            if url in seen:
                return {'url': initial, 'status': 0, 'error': 'redirect_loop', 'chain': chain}, ''
            seen.add(url)
            try:
                response = self.opener.open(urllib.request.Request(url, headers={
                    'User-Agent': 'KafkaLocalSEOAcceptance/2.0', 'Cache-Control': 'no-cache, no-store', 'Pragma': 'no-cache',
                    'Accept': 'text/html,application/xml,text/plain'}), timeout=60)
            except urllib.error.HTTPError as e:
                response = e
            except Exception as e:
                return {'url': initial, 'status': 0, 'error': str(e), 'chain': chain}, ''
            with response:
                entry = {'url': url, 'status': response.code, 'location': response.headers.get('Location', '')}
                chain.append(entry)
                if response.code in (301, 302, 303, 307, 308) and entry['location']:
                    url = U.urljoin(url, entry['location'])
                    continue
                raw = response.read(8_000_001)
                body = raw.decode('utf-8', 'replace')
                result = {'url': initial, 'final_url': url, 'status': response.code, 'chain': chain,
                          'fetched_at': dt.datetime.now(dt.timezone.utc).isoformat(), 'body_sha256': hashlib.sha256(raw).hexdigest(),
                          'content_type': response.headers.get('Content-Type', ''), 'x_robots_tag': response.headers.get('X-Robots-Tag', ''),
                          'truncated': len(raw) > 8_000_000}
                if 'html' in result['content_type']:
                    page = old.Page(url)
                    page.feed(body)
                    result.update(page.result())
                    result['product_ids'] = re.findall(r'data-id-product="(\d+)"', body)
                return result, body
        return {'url': initial, 'status': 0, 'error': 'too_many_redirects', 'chain': chain}, ''


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--output', type=Path, required=True)
    parser.add_argument('--before', type=Path, required=True)
    parser.add_argument('--workers', type=int, choices=[1, 2, 3, 4], default=4)
    args = parser.parse_args()
    out = args.output
    # Refuse resumed/cached evidence; every acceptance run starts with new HTTP responses.
    if (out / 'fresh-http.jsonl').exists():
        raise RuntimeError('Use a new evidence folder. Reusing HTTP output is forbidden.')
    catalog = read(out / 'target-public-catalog.json')
    before = read(args.before)
    rows = {key(r): r for r in catalog['rows']}
    eligible_by_url = {r['url']: r for r in catalog['rows'] if r['eligible']}
    eligible_alternates = {}
    for r in eligible_by_url.values():
        eligible_alternates.setdefault((r['entity'], str(r['id'])), set()).add(r['url'])
    source = read(BASELINE / 'source-public-catalog.json')
    preserved = {key(r): r for r in source['rows']}
    languages = {l['iso_code']: l for l in catalog['languages'] if l['active']}
    source_http = {r['url']: r for r in (json.loads(s) for s in (BASELINE / 'source-http.jsonl').read_text(encoding='utf-8').splitlines())}
    plan = read(BASELINE / 'crawl-plan.json')
    failures, missing, checks, metadata_changes = [], [], [], []
    repaired_titles = {(r['page'], 'cs'): (r['title'], r['new_title']) for r in read(out / 'czech-titles.json')['changes']}

    def check(name, passed, detail=''):
        checks.append({'name': name, 'passed': bool(passed), 'detail': detail})
        if not passed:
            failures.append({'check': name, 'detail': detail})

    # Catalog metadata must remain byte-for-byte unchanged by this repair (Czech system defaults handled separately).
    for r in before['rows']:
        after = rows.get(key(r))
        fields = ['name', 'link_rewrite', 'meta_title', 'meta_description', 'id_category_default', 'ean13', 'active', 'visibility']
        if r['entity'] == 'system':
            fields += ['title', 'description', 'url_rewrite']
        changed = [f for f in fields if r.get(f) != (after or {}).get(f)]
        if r['entity'] == 'system' and 'title' in changed and repaired_titles.get((r['id'], r['language'])) == (r.get('title'), (after or {}).get('title')):
            changed.remove('title')
        if changed:
            metadata_changes.append({'entity': key(r), 'fields': changed})
    check('catalog_metadata_preserved', not metadata_changes, metadata_changes[:20])
    expected_config = read(ROOT / 'scripts/migration-seo-policy.json')['configuration']
    check('managed_settings', all(str(catalog['configuration'].get(k)) == v for k, v in expected_config.items()), catalog['configuration'])
    check('czech_default_language', any(l['iso_code'] == 'cs' and int(l['id_lang']) == int(catalog['configuration']['PS_LANG_DEFAULT']) for l in catalog['languages']))

    urls = set()
    for r in plan:
        urls.add(r['legacy_target_url'])
        if r.get('native_target_url'):
            urls.add(r['native_target_url'])
        identity = old.identify(r['source_url'])
        current = rows.get(identity) if identity else None
        if current and current['language'] in languages:
            urls.add(current['url'])
    pagination = read(BASELINE / 'pagination-checks.json')
    for p in pagination:
        u = p['legacy_target_url']
        urls.update([u, u.split('?')[0], u.replace('?p=2', '?page=2'), u.replace('?p=2', '?p=2&order=product.name.asc')])
    # Explicit wrong-slug, missing-prefix, query controller and optional EAN/combination probes from actual data.
    products = [r for r in catalog['rows'] if r['entity'] == 'product' and r['eligible']]
    ean_products = [r for r in products if r.get('ean13')]
    variant = next((r for r in products if r['id'] == 3454 and r['language'] == 'cs'), None)
    probes = []
    for iso in languages:
        p = next(r for r in products if r['language'] == iso)
        wrong = re.sub(r'(/\d+)-[^/]+\.html$', r'\1-wrong-slug.html', p['url'])
        probes += [(wrong, p['url']), (HOST + '/index.php?' + U.urlencode({'controller': 'product', 'id_product': p['id'], 'id_lang': languages[iso]['id_lang']}), p['url'])]
        urls.add(HOST + '/?id_lang=' + str(languages[iso]['id_lang']))
        # The catalog currently has no EAN values. Exercise accepting an optional EAN URL without changing a row.
        probes.append((p['url'].replace('.html', '-1234567890123.html'), p['url']))
    if variant:
        probes += [(variant['url'].replace('/cs/', '/'), variant['url'])]
        urls.add(variant['url'] + '?id_product_attribute=' + str(variant['cache_default_attribute']))
        flat_variant = HOST + '/cs/' + str(variant['id']) + '-' + str(variant['cache_default_attribute']) + '-' + variant['link_rewrite'] + '.html'
        probes.append((flat_variant, variant['url'] + '?id_product_attribute=' + str(variant['cache_default_attribute'])))
    for p in ean_products[:9]:
        urls.add(p['url'])
        without = p['url'].replace('-' + p['ean13'] + '.html', '.html')
        if without != p['url']:
            probes.append((without, p['url']))
    for url, expected in probes:
        urls.update([url, expected])
    urls.update([HOST + '/', HOST + '/cs/', HOST + '/sk/', HOST + '/de/', HOST + '/cs/seo-acceptance-definitely-missing', HOST + '/1_index_sitemap.xml', HOST + '/sitemap.xml', HOST + '/robots.txt'])
    for r in catalog['rows']:
        if r['entity'] == 'system' and r['language'] == 'cs' and r['id'] in ('cart', 'authentication', 'order-confirmation', 'contact', 'pagenotfound'):
            urls.add(r['url'])
    print(json.dumps({'phase': 'fresh_http', 'planned': len(urls)}), flush=True)
    results, bodies = {}, {}
    state = threading.local()

    def fetch(url):
        if not hasattr(state, 'client'):
            state.client = Client()
        result, body = state.client.fetch(url)
        # HTML is fully parsed and hashed by Client. Only XML bodies are used below; do not retain
        # hundreds of unused HTML documents in completed futures on memory-constrained desktops.
        return result, body if 'xml' in result.get('content_type', '') else ''

    with (out / 'fresh-http.jsonl').open('x', encoding='utf-8') as stream:
        def store(result, body):
            results[result['url']] = result
            if body:
                bodies[result['url']] = body
            stream.write(json.dumps(result, ensure_ascii=False) + '\n')
            stream.flush()

        # Native PS9 translation catalogue creation can race on a language's first request.
        # Initialize active language storefronts serially; keep these fresh responses as evidence.
        warmup = []
        for iso in languages:
            result, body = fetch(HOST + '/' + iso + '/')
            store(result, body)
            warmup.append({'url': result['url'], 'status': result['status']})
            check('serial_language_warmup', result['status'] == 200 and len(result['chain']) == 1, result['url'])
        save(out / 'language-warmup.json', warmup)
        with concurrent.futures.ThreadPoolExecutor(max_workers=args.workers) as pool:
            for i, (result, body) in enumerate(pool.map(fetch, sorted(urls - results.keys())), len(results) + 1):
                store(result, body)
                if i % 100 == 0:
                    print(json.dumps({'phase': 'fresh_http', 'completed': i, 'planned': len(urls)}), flush=True)
        # Verify every hreflang destination advertised by the checked HTML, fresh within this run.
        alternates = {u for r in results.values() if r['status'] == 200 for u in r.get('hreflang', {}).values()}
        extras = sorted(alternates - results.keys())
        print(json.dumps({'phase': 'hreflang_destinations', 'additional': len(extras)}), flush=True)
        with concurrent.futures.ThreadPoolExecutor(max_workers=args.workers) as pool:
            for result, body in pool.map(fetch, extras):
                store(result, body)

        client = Client()
        # XML content validation covers all eligible rows, not just sampled HTTP pages.
        ns = {'s': 'http://www.sitemaps.org/schemas/sitemap/0.9', 'x': 'http://www.w3.org/1999/xhtml'}
        index_result = results[HOST + '/1_index_sitemap.xml']
        sitemap_urls, xml_urls = [], []
        try:
            index = ET.fromstring(bodies[index_result['url']])
            sitemap_urls = [x.text for x in index.findall('s:sitemap/s:loc', ns)]
            check('sitemap_index_available', index_result['status'] == 200 and bool(sitemap_urls), sitemap_urls)
            check('sitemap_alias', results[HOST + '/sitemap.xml']['status'] == 200 and bodies[HOST + '/sitemap.xml'] == bodies[index_result['url']])
            for url in sitemap_urls:
                result, body = client.fetch(url)
                store(result, body)
                check('sitemap_http', result['status'] == 200 and len(result['chain']) == 1, url)
                tree = ET.fromstring(body)
                (out / U.urlsplit(url).path.lstrip('/')).write_text(body, encoding='utf-8')
                for node in tree.findall('s:url', ns):
                    page_url = node.findtext('s:loc', namespaces=ns)
                    xml_urls.append(page_url)
                    alternates_xml = {a.get('href') for a in node.findall('x:link', ns)}
                    entry = eligible_by_url.get(page_url)
                    expected = eligible_alternates.get((entry['entity'], str(entry['id'])), set()) if entry else set()
                    if alternates_xml != expected:
                        failures.append({'check': 'sitemap_hreflang', 'detail': page_url})
            eligible_urls = {r['url'] for r in catalog['rows'] if r['eligible']}
            check('sitemap_exact_eligible_content', set(xml_urls) == eligible_urls and len(xml_urls) == len(set(xml_urls)),
                  {'urls': len(xml_urls), 'eligible': len(eligible_urls), 'missing': sorted(eligible_urls - set(xml_urls))[:20], 'unexpected': sorted(set(xml_urls) - eligible_urls)[:20]})
            check('sitemap_active_language_coverage', {U.urlsplit(u).path.split('/')[1] for u in xml_urls} == set(languages))
        except Exception as e:
            check('sitemap_xml', False, str(e))
        (out / '1_index_sitemap.xml').write_text(bodies.get(HOST + '/1_index_sitemap.xml', ''), encoding='utf-8')

    comparisons = []
    for r in plan:
        response = results[r['legacy_target_url']]
        historic = source_http.get(r['source_url'], {})
        identity = old.identify(r['source_url'])
        current = rows.get(identity) if identity else None
        classification = 'source_already_unavailable' if historic.get('status') == 404 else 'system_or_existing_content'
        if historic.get('status') == 200 and identity:
            if not current or current['language'] not in languages or not int(current.get('active', 0)):
                classification = 'missing_or_inactive_content'
                missing.append({'url': r['source_url'], 'entity': identity, 'reason': 'missing' if not current else 'inactive', 'target_status': response['status']})
                if response['status'] != 404:
                    failures.append({'check': 'missing_content_must_stay_404', 'detail': r['legacy_target_url']})
            else:
                routing_ok = response['status'] == 200 and U.urlsplit(response.get('final_url', '')).path == U.urlsplit(current['url']).path
                classification = 'routing_pass' if routing_ok else 'routing_regression'
                check('legacy_live_existing_content', routing_ok,
                      {'url': r['legacy_target_url'], 'expected': current['url'], 'status': response['status']})
        comparisons.append({'source_url': r['source_url'], 'legacy_url': r['legacy_target_url'], 'historical_source_status': historic.get('status'),
                            'fresh_status': response['status'], 'final_url': response.get('final_url'), 'classification': classification, 'chain': response['chain']})

    for url, expected in probes:
        r = results[url]
        check('single_permanent_normalization', r['status'] == 200 and r.get('final_url') == expected and len(r['chain']) == 2 and r['chain'][0]['status'] == 301,
              {'url': url, 'expected': expected, 'chain': r['chain']})
    for p in pagination:
        legacy = results[p['legacy_target_url']]
        native = results[p['legacy_target_url'].replace('?p=2', '?page=2')]
        first = results[p['legacy_target_url'].split('?')[0]]
        check('legacy_pagination', legacy['status'] == native['status'] == 200 and legacy.get('product_ids') == native.get('product_ids') and legacy.get('product_ids') != first.get('product_ids')
              and len(legacy['chain']) == 2 and legacy['chain'][0]['status'] == 301 and legacy.get('canonical') == native.get('canonical')
              and '?page=2' in ''.join(native.get('canonical', [])), {'legacy_url': legacy['url'], 'chain': legacy['chain'], 'canonical': legacy.get('canonical')})
        ordered = results[p['legacy_target_url'].replace('?p=2', '?p=2&order=product.name.asc')]
        check('pagination_preserves_other_parameters', ordered['status'] == 200 and 'order=product.name.asc' in ordered.get('final_url', '') and len(ordered['chain']) == 2, ordered['url'])

    technical = []
    for url, r in results.items():
        if 'noindex' not in r.get('x_robots_tag', ''):
            failures.append({'check': 'localhost_noindex', 'detail': url})
        if r['status'] == 0 or r.get('truncated'):
            failures.append({'check': 'http_fetch', 'detail': {'url': url, 'error': r.get('error', 'truncated')}})
        if r['status'] >= 500:
            failures.append({'check': 'http_server_error', 'detail': {'url': url, 'status': r['status']}})
        if len(r['chain']) > 2:
            failures.append({'check': 'redirect_chain', 'detail': {'url': url, 'chain': r['chain']}})
        if r['status'] != 200 or 'html' not in r.get('content_type', ''):
            continue
        root_language_redirect = url == HOST + '/'
        if any(h['status'] in (302, 307) for h in r['chain'][:-1]) and not root_language_redirect and 'id_lang=' not in url:
            failures.append({'check': 'unexpected_temporary_redirect', 'detail': {'url': url, 'chain': r['chain']}})
        canonical = r.get('canonical', [])
        if r.get('body_id') == 'cart':
            # Native private cart pages deliberately omit canonical. Preserve their noindex behavior.
            check('private_cart_noindex', 'noindex' in r.get('robots', ''), url)
        elif len(canonical) != 1 or U.urlsplit(canonical[0]).netloc != 'localhost:8083':
            failures.append({'check': 'one_local_canonical', 'detail': url})
        elif U.urlsplit(canonical[0]).path != U.urlsplit(r.get('final_url', '')).path:
            failures.append({'check': 'canonical_same_page', 'detail': {'url': url, 'canonical': canonical}})
        # HTML title text is whitespace-normalized by Page; normalize OG the same way without altering stored metadata.
        if len(re.findall(r'kafka\s+monster', r.get('title', ''), re.I)) != 1 or re.sub(r'\s+', ' ', r.get('og_title', '')).strip() != r.get('title'):
            failures.append({'check': 'brand_title_once_and_og', 'detail': {'url': url, 'title': r.get('title'), 'og': r.get('og_title')}})
        if {code.lower() for code in r.get('hreflang', {})} != {l['language_code'].lower() for l in languages.values()}:
            failures.append({'check': 'hreflang_languages', 'detail': url})
        for code, alt in r.get('hreflang', {}).items():
            ar = results.get(alt)
            if not ar or ar['status'] != 200 or len(ar['chain']) != 1:
                failures.append({'check': 'hreflang_direct_destination', 'detail': {'url': url, 'alternate': alt, 'status': ar.get('status') if ar else None}})
            elif canonical and old.identify(canonical[0]) and old.identify(alt) and old.identify(canonical[0])[:2] != old.identify(alt)[:2]:
                failures.append({'check': 'hreflang_same_entity', 'detail': {'url': url, 'alternate': alt}})
            elif ar and r.get('hreflang') and ar.get('hreflang') != r.get('hreflang'):
                failures.append({'check': 'hreflang_reciprocity', 'detail': {'url': url, 'alternate': alt}})
        technical.append({'url': url, 'title': r.get('title'), 'canonical': canonical, 'hreflang': r.get('hreflang'), 'chain': r['chain']})
    # Public Czech system title repairs are tested through fresh HTML; order confirmation requires a real order, so no synthetic one is created.
    for page, title in [('cart', 'Košík'), ('authentication', 'Přihlášení')]:
        row = rows.get(('system', page, 'cs'))
        if row:
            check('czech_system_title_' + page, results[row['url']].get('title') == title + ' - Kafka Monster', results[row['url']].get('title'))
    check('root_language_selection_stays_temporary', results[HOST + '/']['chain'][0]['status'] == 302, results[HOST + '/']['chain'])
    for iso, language in languages.items():
        r = results[HOST + '/?id_lang=' + str(language['id_lang'])]
        check('explicit_legacy_language_choice', r['status'] == 200 and len(r['chain']) == 2 and r['chain'][0]['status'] == 302 and r.get('final_url') == HOST + '/' + iso + '/', r['chain'])
    check('unknown_page_404', results[HOST + '/cs/seo-acceptance-definitely-missing']['status'] == 404)

    old.write_csv(out / 'legacy-url-results.csv', comparisons)
    old.write_csv(out / 'missing-content.csv', missing)
    old.write_csv(out / 'failures.csv', failures)
    save(out / 'checks.json', checks)
    save(out / 'technical-pages.json', technical)
    save(out / 'metadata-changes.json', metadata_changes)
    path_changes = [{'entity': k, 'old': r['url'], 'current': rows[k]['url'], 'active_language': r['language'] in languages,
                     'content_changed': any(str(r.get(f) or '') != str(rows[k].get(f) or '') for f in ('link_rewrite', 'category_slug', 'ean13', 'id_category_default', 'name'))}
                    for k, r in preserved.items() if k in rows and r['entity'] != 'system' and U.urlsplit(r['url']).path != U.urlsplit(rows[k]['url']).path]
    save(out / 'baseline-catalog-path-differences.json', path_changes)
    check('preserved_catalog_path_structure', not any(c['active_language'] and not c['content_changed'] for c in path_changes),
          {'changed': len(path_changes), 'inactive_language': sum(not c['active_language'] for c in path_changes), 'content_changed': sum(c['content_changed'] for c in path_changes)})
    summary = {'finished_at': dt.datetime.now(dt.timezone.utc).isoformat(), 'target': HOST, 'fresh_http_requests': len(results),
               'legacy_urls': len(plan), 'historical_source_evidence': str(BASELINE), 'production_requests': 0,
               'eligible_sitemap_urls': len(xml_urls), 'sitemap_files': len(sitemap_urls), 'active_languages': sorted(languages),
               'catalog_rows': len(rows), 'routing_regressions': sum(r['classification'] == 'routing_regression' for r in comparisons),
               'missing_content_urls': len(missing), 'failures': len(failures), 'passed': not failures}
    save(out / 'summary.json', summary)
    save(out / 'checks.json', checks)
    old.write_csv(out / 'failures.csv', failures)
    report = ['# Fresh local SEO acceptance — ' + summary['finished_at'], '', '**' + ('PASS' if summary['passed'] else 'FAIL') + '**', '',
              f"Target: {HOST}. Production requests: 0. Fresh HTTP records: {len(results)}. Baseline legacy URLs: {len(plan)}.", '',
              f"Routing regressions: {summary['routing_regressions']}. Missing/inactive content URLs: {len(missing)} (separate from routing). Actionable failures: {len(failures)}.", '',
              f"Catalog metadata preserved: {not metadata_changes}. Sitemap: {len(xml_urls)} eligible URLs, {len(sitemap_urls)} files, languages {', '.join(languages)}.", '',
              '## Actionable failures', '']
    report += ['- ' + f['check'] + ': ' + json.dumps(f['detail'], ensure_ascii=False) for f in failures[:60]] or ['None.']
    report += ['', 'Full failures: `failures.csv`. Missing data: `missing-content.csv`. Redirects: `legacy-url-results.csv`. Fresh HTTP and headers: `fresh-http.jsonl`.', '',
               'Historical source HTTP was used only to classify the preserved baseline. All target evidence in this folder was fetched during this run; no earlier target HTTP cache was read.', '',
               'Sitemap membership and alternate links are checked for every XML entry against existing eligible local records. HTTP coverage is the baseline plus normalization, language and pagination probes; it is not every catalog URL.', '',
               'No new SQL, content, translations, media, order or production change is part of this audit. Local noindex protection must remain enabled. Production cutover and Search Console require separate authorization.']
    (out / 'REPORT.md').write_text('\n'.join(report) + '\n', encoding='utf-8')
    print(json.dumps(summary), flush=True)
    return 0 if summary['passed'] else 1


if __name__ == '__main__':
    raise SystemExit(main())
