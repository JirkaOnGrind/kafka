"""Read-only template/core-reference comparison; exports paths and hashes, not content."""
import hashlib
import json
from collections import Counter, defaultdict
from datetime import datetime, timezone
from pathlib import Path
from zipfile import ZipFile
from audit_ftp import SOURCE_DEFAULT, stable_read

HERE = Path(__file__).resolve().parent
PREFIXES = ('pdf/', 'themes/default-bootstrap/')
SUFFIXES = {'.tpl', '.css', '.js', '.php', '.scss', '.html', '.txt'}

def main():
    result = {'observed_at_utc': datetime.now(timezone.utc).isoformat(),
              'scope': 'Selected template/theme code; no customer uploads, logs or images', 'groups': {}}
    with ZipFile(HERE / 'evidence/prestashop-1.6.1.13-reference.zip') as ref:
        members = {n.split('/', 1)[1]: n for n in ref.namelist() if '/' in n and not n.endswith('/')}
        for prefix in PREFIXES:
            group = {'identical': 0, 'newline_only': 0, 'different': [], 'pending': [], 'unstable': [], 'additional': []}
            selected = {rel: member for rel, member in members.items()
                        if rel.startswith(prefix) and Path(rel).suffix in SUFFIXES}
            for rel, member in selected.items():
                path = SOURCE_DEFAULT / rel
                if not path.exists():
                    group['pending'].append(rel)
                    continue
                raw = stable_read(path)
                if raw is None:
                    group['unstable'].append(rel)
                    continue
                original = ref.read(member)
                if raw == original:
                    group['identical'] += 1
                elif raw.replace(b'\r\n', b'\n') == original.replace(b'\r\n', b'\n'):
                    group['newline_only'] += 1
                else:
                    group['different'].append({'path': rel, 'bytes': len(raw),
                        'sha256': hashlib.sha256(raw).hexdigest(), 'reference_sha256': hashlib.sha256(original).hexdigest()})
            directory = SOURCE_DEFAULT / prefix
            if directory.exists():
                for path in directory.rglob('*'):
                    if not path.is_file() or path.suffix not in SUFFIXES or path.is_symlink():
                        continue
                    rel = path.relative_to(SOURCE_DEFAULT).as_posix()
                    if rel not in selected:
                        raw = stable_read(path)
                        group['additional'].append({'path': rel, 'stable_available': raw is not None,
                            'sha256': hashlib.sha256(raw).hexdigest() if raw is not None else None})
            result['groups'][prefix] = group
    theme_group = result['groups']['themes/default-bootstrap/']
    theme_group['additional_top_groups'] = dict(Counter(
        r['path'][len('themes/default-bootstrap/'):].split('/')[0] for r in theme_group['additional']))
    mail_stats = defaultdict(lambda: {'stable_pairs': 0, 'identical': 0, 'different_names': [], 'pending_pairs': 0})
    mail_dir = SOURCE_DEFAULT / 'themes/default-bootstrap/mails'
    if mail_dir.exists():
        for path in mail_dir.rglob('*'):
            if not path.is_file() or path.suffix not in {'.tpl', '.html', '.txt'}:
                continue
            rel = path.relative_to(mail_dir)
            if len(rel.parts) != 2:
                continue
            a, b = stable_read(path), stable_read(SOURCE_DEFAULT / 'mails' / rel)
            stats = mail_stats[rel.parts[0]]
            if a is None or b is None:
                stats['pending_pairs'] += 1
            else:
                stats['stable_pairs'] += 1
                if a == b:
                    stats['identical'] += 1
                else:
                    stats['different_names'].append(rel.name)
    result['theme_mails_vs_root'] = dict(mail_stats)
    (HERE / 'evidence/template-code-audit.json').write_text(json.dumps(result, ensure_ascii=False, indent=2), encoding='utf-8')
    print(json.dumps({prefix: {
        'identical': group['identical'], 'newline_only': group['newline_only'],
        'different_paths': [r['path'] for r in group['different']],
        'pending_count': len(group['pending']), 'unstable_count': len(group['unstable']),
        'additional_count': len(group['additional']),
        'additional_top_groups': group.get('additional_top_groups', {}),
    } for prefix, group in result['groups'].items()}, ensure_ascii=False, indent=2))
    print(json.dumps({'theme_mails_vs_root': result['theme_mails_vs_root']}, ensure_ascii=False, indent=2))

if __name__ == '__main__':
    main()
