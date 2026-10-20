"""Regression checks for actual mixed catalog types; read-only private fixture, no shop connection."""
import argparse
import copy
import importlib.util
import json
from pathlib import Path

spec = importlib.util.spec_from_file_location('category_planner', Path(__file__).with_name('plan-category-assignments.py'))
planner = importlib.util.module_from_spec(spec)
spec.loader.exec_module(planner)


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--catalog', type=Path, required=True)
    args = parser.parse_args()
    catalog = json.loads(args.catalog.read_text(encoding='utf-8-sig'))
    original = copy.deepcopy(catalog)
    result = planner.plan(catalog)
    targets = {}
    for row in result['assignments']: targets.setdefault(row['id_product'], set()).add(row['path'])
    checks = {
        'decorated collar is not loose pyramids': 'stylove-doplnky/obojky' in targets[1583] and 'modni-doplnky/pyramidy' not in targets[1583],
        'spike earring is not loose hardware': 'modni-doplnky/hroty' not in targets.get(659, set()),
        'Punkavec fanzine is not same-name band merch': 'stylove-doplnky/fanziny' in targets[2392] and not any(p.startswith('merch/kapely') for p in targets[2392]),
        'custom caps do not become ordinary hats': 'obleceni/cepice' not in targets.get(179, set()),
        'custom button opener stays under print services': 'merch/vlastni-potisk/placky' in targets[737] and 'stylove-doplnky/otviraky' not in targets[737],
        'generic PND title uses explicit bag description': 'stylove-doplnky/tasky' in targets[1723],
        'all active catalog products become reachable': result['missing_navigation_after'] == 0 and not result['unresolved'],
        'all managed categories have native catalog content': all(r['after'] for r in result['counts']),
        'historical badge landing retains complete catalog': next(r['after'] for r in result['legacy_counts'] if r['id_category'] == 3) == 267,
        'planner cannot modify source catalog': catalog == original,
    }
    ambiguous = copy.deepcopy(catalog)
    next(p for p in ambiguous['products'] if int(p['id_product']) == 424)['name'] = 'Unspecified accessory'
    try:
        planner.plan(ambiguous)
        checks['ambiguous active split blocks application'] = False
    except ValueError:
        checks['ambiguous active split blocks application'] = True
    wrong_source = copy.deepcopy(catalog)
    next(c for c in wrong_source['categories'] if int(c['id_category']) == 93)['link_rewrite'] = 'unrelated-category'
    try:
        planner.plan(wrong_source)
        checks['unexpected source identity blocks application'] = False
    except ValueError:
        checks['unexpected source identity blocks application'] = True
    print(json.dumps(dict(passed=all(checks.values()), checks=checks), ensure_ascii=False))
    if not all(checks.values()): raise SystemExit(1)


if __name__ == '__main__': main()
