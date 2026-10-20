"""Plan additive category links from catalog evidence; never change product/default-category URLs."""
import argparse
import collections
import csv
import hashlib
import html
import json
from pathlib import Path
import re
import unicodedata


def norm(value):
    value = html.unescape(re.sub(r'<[^>]+>', ' ', value or ''))
    return re.sub(r'[^a-z0-9]+', ' ', unicodedata.normalize('NFKD', value).encode('ascii', 'ignore').decode().lower()).strip()


def plan(data):
    products = {int(p['id_product']): p for p in data['products']}
    categories = {int(c['id_category']): c for c in data['categories']}
    registry = data['registry']
    legacy_targets = {'legacy/plackyspendlikypiny': 3, 'legacy/cepice-satky': 32, 'legacy/taskypenezenky': 58}
    targets = registry | legacy_targets
    links = collections.defaultdict(set)
    by_product = collections.defaultdict(set)
    for row in data['links']:
        cid, pid = int(row['id_category']), int(row['id_product'])
        links[cid].add(pid)
        by_product[pid].add(cid)
    public = {pid for pid, p in products.items() if int(p['active']) and p['visibility'] in ('both', 'catalog')}
    descendants = {}
    for path, cid in targets.items():
        c = categories[cid]
        descendants[path] = {i for i, r in categories.items() if int(c['nleft']) <= int(r['nleft']) and int(r['nright']) <= int(c['nright'])}
    managed_scope = set().union(*(descendants[path] for path in registry))

    # Confirm every historical split source by both its preserved identity and slug.
    sources = {3: 'plackyspendlikypiny', 10: 'vlastni-potisk-cenik', 32: 'cepice-satky', 33: 'zimni', 34: 'ksiltovky',
               58: 'taskypenezenky', 59: 'platene-tasky', 60: 'vakybatohytasky', 61: 'kapsy-na-pasek',
               93: 'obojky-kravaty', 98: 'knihyfanziny', 102: 'hrotypyramidycvoky'}
    for cid, slug in sources.items():
        if categories.get(cid, {}).get('link_rewrite') != slug:
            raise ValueError(f'Historical source identity changed: {cid}; review this snapshot first.')

    planned = {}
    unresolved = []

    def has(pid, path):
        return bool(by_product[pid] & descendants[path]) or (pid, targets[path]) in planned

    def add(pid, path, reason, source):
        if path not in targets:
            raise ValueError('Missing managed category: ' + path)
        if not has(pid, path):
            planned[pid, targets[path]] = dict(id_product=pid, id_category=targets[path], path=path,
                                             product_name=products[pid]['name'], active_catalog=pid in public,
                                             reason=reason, source_categories=sorted(source))

    def split(pid, path, source, reason):
        if path:
            add(pid, path, reason, source)
        elif pid in public:
            unresolved.append(dict(id_product=pid, name=products[pid]['name'], source_categories=sorted(source)))

    for pid, product in products.items():
        source = by_product[pid]
        name = norm(product['name'])
        short = norm(product['description_short'])
        description = norm(product['description_short'] + ' ' + product['description'])
        if source & {32, 33, 34}:
            if source & {33, 34} or re.match(r'^(cepice|ksiltovka|bekovka)\b', name):
                split(pid, 'obleceni/cepice', source, 'Historical winter-cap/baseball-cap category or explicit hat title')
            elif 126 in source or name.startswith('satek'):
                split(pid, 'obleceni/satky', source, 'Historical scarf category or explicit scarf title')
            else:
                split(pid, None, source, '')
        if source & {58, 59, 60, 61}:
            if 62 in source or name.startswith('penezenka'):
                split(pid, 'stylove-doplnky/penezenky', source, 'Historical wallet category or wallet title')
            elif source & {59, 60, 61} or re.search(r'\b(taska|kabelka|batoh|vak|kapsa)\b', name + ' ' + short):
                split(pid, 'stylove-doplnky/tasky', source, 'Historical bag/backpack/belt-pouch category or explicit bag description')
            else:
                split(pid, None, source, '')
        if 93 in source:
            target = 'stylove-doplnky/obojky' if name.startswith('obojek ') else 'obleceni/kravaty' if name.startswith('kravata ') else None
            split(pid, target, source, 'Reviewed collar/tie split by explicit product type in title')
        if 102 in source:
            target = 'modni-doplnky/hroty' if re.match(r'^hroty?\b', name) else 'modni-doplnky/pyramidy' if name.startswith('pyramid') else 'modni-doplnky/cvoky' if name.startswith('cvok') else None
            split(pid, target, source, 'Reviewed loose spike/pyramid/stud split; decorated finished accessories excluded')
        if 98 in source:
            if re.search(r'\bfanzin\b', description):
                split(pid, 'stylove-doplnky/fanziny', source, 'Product description explicitly identifies a fanzine, including Punkavec sets')
            elif name.startswith('kniha') or re.match(r'^(knizka|kniha)\b', short or description):
                split(pid, 'stylove-doplnky/knihy', source, 'Book title or explicit book description, including reviewed Neklan publications')
            else:
                split(pid, None, source, '')
        if 3 in source and not any(has(pid, path) for path in ('modni-doplnky/placky', 'modni-doplnky/spendliky', 'modni-doplnky/piny')):
            target = 'modni-doplnky/placky' if name.startswith('placka ') or short.startswith('placka ') else 'modni-doplnky/spendliky' if name.startswith('spendlik ') else 'modni-doplnky/piny' if name.startswith('pin ') else None
            split(pid, target, source, 'Explicit badge/pin product type; pin-backed badges remain Placky')

        # Four reviewed custom-print button variants were attached only to the custom parent.
        if 10 in source and pid in (734, 735, 736, 737):
            add(pid, 'merch/vlastni-potisk/placky', 'Reviewed 58 mm custom button/magnet/mirror/opener variants with quantity price list', source)

        # Complete clear band cross-listings while keeping fanzines separate from same-name band merch.
        if pid in public and 25 not in source and not (98 in source or re.search(r'\b(fanzin|kniha|knizka)\b', short)):
            for band in ('plesnivy-fazole', 'neparkovat-vjezd', 'stamp-out-system', 'pruser', 'adhd-helmut', 'the-punkavec', 'indianska-rezervace'):
                pattern = band.replace('-', ' ')
                if re.search(r'\b' + re.escape(pattern) + r'\b', name):
                    add(pid, 'merch/kapely/' + band, 'Exact client-band motif in title; reviewed missing merchandise cross-listing', source)

    # Keep complete historical mixed-category landing contents after their children move to the new tree.
    # These are exact original descendant sets from migration/category-mapping.csv, not new category guesses.
    legacy_scopes = {3: {3, 4, 75, 124}, 32: {32, 33, 34, 126}, 58: {58, 59, 60, 61, 62}}
    legacy_counts = []
    for path, cid in legacy_targets.items():
        original = set().union(*(links[i] for i in legacy_scopes[cid]))
        current = set().union(*(links[i] for i in descendants[path]))
        for pid in sorted(original):
            add(pid, path, 'Preserve full original mixed-category landing contents after child reparenting; exact historical memberships', legacy_scopes[cid])
        legacy_counts.append(dict(path=path, id_category=cid, before=len(current & public), after=len(original & public)))

    def reachable(extra):
        covered = {pid for pid, cids in by_product.items() if cids & managed_scope}
        return covered | {pid for pid, cid in extra if cid in managed_scope}

    before_gap = public - reachable({})
    after_gap = public - reachable(planned)
    if after_gap or unresolved:
        raise ValueError(json.dumps(dict(unresolved=unresolved, uncovered_products=sorted(after_gap)), ensure_ascii=False))
    projected = {cid: set(pids) for cid, pids in links.items()}
    for pid, cid in planned:
        projected.setdefault(cid, set()).add(pid)
    counts = []
    for path, cid in registry.items():
        scope = descendants[path]
        before = set().union(*(links[c] for c in scope)) & public
        after = set().union(*(projected.get(c, set()) for c in scope)) & public
        counts.append(dict(path=path, id_category=cid, before=len(before), after=len(after)))
    return dict(schema=1, generated_from=data['generated_at'], expected_fingerprints=data['fingerprints'],
                expected_registry=registry, legacy_targets=legacy_targets, legacy_counts=legacy_counts, products=len(products), public_products=len(public),
                missing_navigation_before=len(before_gap), missing_navigation_after=len(after_gap), unresolved=unresolved,
                assignments=sorted(planned.values(), key=lambda r: (r['id_category'], r['id_product'])), counts=counts,
                policy='Add category_product links only; preserve every existing link, product default, metadata, category slug, activity and language')


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--catalog', type=Path, required=True)
    parser.add_argument('--output', type=Path, required=True)
    args = parser.parse_args()
    raw = args.catalog.read_bytes()
    result = plan(json.loads(raw.decode('utf-8-sig')))
    result['catalog_sha256'] = hashlib.sha256(raw).hexdigest()
    args.output.write_text(json.dumps(result, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
    for name, rows in [('category-counts.csv', result['counts']), ('product-assignments.csv', [r for r in result['assignments'] if r['active_catalog']])]:
        with (args.output.parent / name).open('w', encoding='utf-8-sig', newline='') as stream:
            writer = csv.DictWriter(stream, fieldnames=list(rows[0]) if rows else ['path'])
            writer.writeheader()
            writer.writerows(rows)
    print(json.dumps({k: result[k] for k in ('products', 'public_products', 'missing_navigation_before', 'missing_navigation_after')}
                     | dict(new_links=len(result['assignments']), affected_products=len({r['id_product'] for r in result['assignments']})), ensure_ascii=False))


if __name__ == '__main__':
    main()
