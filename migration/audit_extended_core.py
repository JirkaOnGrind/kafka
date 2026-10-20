"""Compare additional root/tools PHP with official source, without source execution."""
import hashlib
import json
from datetime import datetime, timezone
from pathlib import Path
from zipfile import ZipFile
from audit_ftp import SOURCE_DEFAULT, stable_read

HERE = Path(__file__).resolve().parent

def main():
    result = {'observed_at_utc': datetime.now(timezone.utc).isoformat(),
              'scope': 'Official referenced root/tools PHP only; excludes unknown configuration files',
              'identical': 0, 'different': [], 'pending': [], 'unstable': [], 'additional_tools_php': []}
    selected = set()
    with ZipFile(HERE / 'evidence/prestashop-1.6.1.13-reference.zip') as ref:
        for member in ref.namelist():
            if '/' not in member or not member.endswith('.php'):
                continue
            rel = member.split('/', 1)[1]
            if not (rel.startswith('tools/') or '/' not in rel):
                continue
            selected.add(rel)
            path = SOURCE_DEFAULT / rel
            if not path.exists():
                result['pending'].append(rel)
                continue
            raw = stable_read(path)
            if raw is None:
                result['unstable'].append(rel)
            elif raw == ref.read(member):
                result['identical'] += 1
            else:
                result['different'].append({'path': rel, 'sha256': hashlib.sha256(raw).hexdigest()})
    tools_dir = SOURCE_DEFAULT / 'tools'
    if tools_dir.exists():
        result['additional_tools_php'] = sorted(path.relative_to(SOURCE_DEFAULT).as_posix()
            for path in tools_dir.rglob('*.php') if path.is_file() and not path.is_symlink()
            and path.relative_to(SOURCE_DEFAULT).as_posix() not in selected)
    (HERE / 'evidence/extended-core-audit.json').write_text(json.dumps(result, ensure_ascii=False, indent=2), encoding='utf-8')
    print(json.dumps({'identical': result['identical'], 'different': result['different'],
                      'pending_count': len(result['pending']), 'pending_paths_sample': result['pending'][:12],
                      'unstable_count': len(result['unstable']), 'additional_tools_php': result['additional_tools_php']}, ensure_ascii=False, indent=2))

if __name__ == '__main__':
    main()
