"""Fresh local category/product acceptance plus exhaustive source-path and metadata comparison."""
import argparse
import collections
import concurrent.futures
import csv
import datetime as dt
import html
import importlib.util
import json
from pathlib import Path
import re
import threading
import urllib.parse as U

ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('local_acceptance', ROOT / 'scripts/audit-local-seo.py')
acceptance = importlib.util.module_from_spec(spec)
spec.loader.exec_module(acceptance)
HOST = 'http://localhost:8083'


def read(path):
    return json.loads(Path(path).read_text(encoding='utf-8-sig'))


def save(path, value):
    Path(path).write_text(json.dumps(value, ensure_ascii=False, indent=2), encoding='utf-8')


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--initial-run', type=Path, required=True)
    parser.add_argument('--final-run', type=Path, required=True)
    parser.add_argument('--seo-evidence', type=Path, required=True)
    parser.add_argument('--output', type=Path, required=True)
    args = parser.parse_args()
    args.output.mkdir(parents=True, exist_ok=False)
    before = read(args.initial_run / 'catalog-before.json')
    after = read(args.final_run / 'catalog-after.json')
    initial_plan = read(args.initial_run / 'assignment-plan.json')
    final_plan = read(args.final_run / 'assignment-plan.json')
    current = read(args.seo_evidence / 'target-public-catalog.json')
    source = read(ROOT / 'migration/evidence/seo-20261019/source-public-catalog.json')
    languages = {r['iso_code'] for r in current['languages'] if int(r['active'])}
    failures = []
    immutable_tables = [table for table in before['fingerprints'] if table != 'category_product']
    protected = {table: before['fingerprints'][table] == after['fingerprints'][table] for table in immutable_tables}
    if not all(protected.values()): failures.append(dict(check='protected_catalog_tables', detail=protected))
    old_links = {(int(r['id_category']), int(r['id_product'])): int(r['position']) for r in before['links']}
    new_links = {(int(r['id_category']), int(r['id_product'])): int(r['position']) for r in after['links']}
    removed_or_changed = [k for k, pos in old_links.items() if new_links.get(k) != pos]
    if removed_or_changed: failures.append(dict(check='original_product_links_preserved', detail=removed_or_changed))

    indexed = {(r['entity'], str(r['id']), r['language']): r for r in current['rows']}
    path_changes, metadata_changes, compared = [], [], collections.Counter()
    for row in source['rows']:
        if row['entity'] not in ('product', 'category') or row['language'] not in languages: continue
        key = row['entity'], str(row['id']), row['language']
        target = indexed.get(key)
        compared[row['entity']] += 1
        if not target or U.unquote(U.urlsplit(row['url']).path) != U.unquote(U.urlsplit(target['url']).path):
            path_changes.append(dict(entity=key, original=row['url'], local=target.get('url') if target else None))
        fields = ['link_rewrite', 'meta_title', 'meta_description']
        if row['entity'] == 'product': fields += ['name', 'id_category_default']
        changed = [f for f in fields if str(row.get(f) or '') != str((target or {}).get(f) or '')]
        if changed: metadata_changes.append(dict(entity=key, fields=changed))
    if path_changes or metadata_changes: failures.append(dict(check='source_urls_and_metadata', paths=len(path_changes), metadata=len(metadata_changes)))
    save(args.output / 'source-url-preservation.json', dict(compared=dict(compared), changed_paths=path_changes, changed_metadata=metadata_changes, protected_tables=protected, original_links_preserved=not removed_or_changed))

    # The repeat classifier must need no further additions and must expose every active catalog product.
    repeat = read(args.final_run / 'repeat-plan.json')
    if repeat['assignments'] or repeat['missing_navigation_after'] or repeat['unresolved']:
        failures.append(dict(check='complete_repeat_classification', detail=repeat['unresolved']))
    categories = {int(r['id_category']): r for r in after['categories']}
    products = {int(r['id_product']): r for r in after['products']}
    public = {i for i, p in products.items() if int(p['active']) and p['visibility'] in ('both', 'catalog')}
    links = collections.defaultdict(set)
    for row in after['links']: links[int(row['id_category'])].add(int(row['id_product']))
    tasks = []
    expected_sets = {}
    for path, cid in (after['registry'] | final_plan['legacy_targets']).items():
        c = categories[cid]
        scope = [i for i, child in categories.items() if int(c['nleft']) <= int(child['nleft']) and int(child['nright']) <= int(c['nright'])]
        expected = set().union(*(links[i] for i in scope)) & public
        url = indexed['category', str(cid), 'cs']['url']
        expected_sets[url] = expected
        tasks.append(dict(kind='category', path=path, id=cid, url=url, expected_count=len(expected)))
    affected = {a['id_product'] for a in initial_plan['assignments'] if a['active_catalog']}
    for pid in sorted(affected):
        source_row = next(r for r in source['rows'] if r['entity'] == 'product' and r['language'] == 'cs' and int(r['id']) == pid)
        original = source_row['url']
        # Exercise the exact original kafka.monster path on the local host, never fetch production.
        url = HOST + U.urlsplit(original).path
        tasks.append(dict(kind='product', id=pid, url=url, original_url=original))
    state = threading.local()

    def fetch(task):
        if not hasattr(state, 'client'): state.client = acceptance.Client()
        result, body = state.client.fetch(task['url'])
        errors = []
        if result['status'] != 200: errors.append('HTTP not 200')
        if result.get('final_url') != task['url']: errors.append('Original URL changed or redirected')
        if result.get('canonical') != [task['url']]: errors.append('Canonical differs from preserved original path')
        if 'noindex' not in result.get('x_robots_tag', ''): errors.append('Missing local noindex protection')
        if task['kind'] == 'category':
            text = re.sub(r'\s+', ' ', html.unescape(re.sub(r'<[^>]+>', ' ', body)))
            count = re.search(r'Počet produktů:\s*([0-9]+(?:[ \u00a0][0-9]{3})*)', text)
            # Native Czech translation uses a separate singular sentence, not the plural count label.
            actual = int(re.sub(r'\s', '', count[1])) if count else 1 if 'Je zde 1 produkt.' in text else 0
            result['expected_count'] = task['expected_count']; result['rendered_count'] = actual
            if actual != task['expected_count']: errors.append('Rendered native product count differs from catalog coverage')
            shown = set(map(int, result.get('product_ids', [])))
            if not shown <= expected_sets[task['url']]: errors.append('Native listing includes unrelated product')
            if task['expected_count'] and not shown: errors.append('Expected products are absent from rendered listing')
        elif not result.get('h1'): errors.append('Missing native product heading')
        return task | result | dict(passed=not errors, failures=errors)

    print(json.dumps(dict(phase='category_and_product_http', planned=len(tasks))), flush=True)
    results = []
    with (args.output / 'fresh-http.jsonl').open('x', encoding='utf-8') as stream, concurrent.futures.ThreadPoolExecutor(max_workers=3) as pool:
        for i, result in enumerate(pool.map(fetch, tasks), 1):
            results.append(result); stream.write(json.dumps(result, ensure_ascii=False) + '\n'); stream.flush()
            if not result['passed']: failures.append(dict(check='fresh_http', url=result['url'], detail=result['failures']))
            if i % 50 == 0: print(json.dumps(dict(phase='category_and_product_http', completed=i, planned=len(tasks))), flush=True)
    listing_rows = [{k: r[k] for k in ('path', 'id', 'url', 'expected_count', 'rendered_count', 'passed')} for r in results if r['kind'] == 'category']
    with (args.output / 'category-listings.csv').open('w', encoding='utf-8-sig', newline='') as stream:
        writer = csv.DictWriter(stream, fieldnames=list(listing_rows[0])); writer.writeheader(); writer.writerows(listing_rows)
    summary = dict(finished_at=dt.datetime.now(dt.timezone.utc).isoformat(), passed=not failures, products=len(products), public_catalog_products=len(public),
                   initially_unreachable=initial_plan['missing_navigation_before'], unreachable_after=repeat['missing_navigation_after'],
                   original_links=len(old_links), final_links=len(new_links), added_links=len(new_links) - len(old_links),
                   compared_source_rows=dict(compared), source_path_changes=len(path_changes), source_metadata_changes=len(metadata_changes),
                   fresh_http=len(results), verified_category_listings=len(listing_rows), verified_affected_products=len(affected), failures=failures)
    save(args.output / 'summary.json', summary)
    print(json.dumps(summary, ensure_ascii=False), flush=True)
    if failures: raise SystemExit(1)


if __name__ == '__main__': main()
