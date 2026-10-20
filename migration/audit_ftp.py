"""Read-only, incremental audit of a downloading PrestaShop FTP copy.

Never executes PHP, SQL or scripts from the copy. Exports aggregate inventory,
public code metadata and known non-secret config flags; no credentials or logs.
Missing files are explicitly pending, not proof of a broken live shop.
"""
import argparse, csv, hashlib, json, os, re, zipfile
from collections import Counter, defaultdict
from datetime import datetime, timezone
from pathlib import Path
import xml.etree.ElementTree as ET

HERE = Path(__file__).resolve().parent
CORE_DIRS = {'classes', 'controllers', 'config', 'Core', 'Adapter'}
CORE_ROOT = {'index.php', 'init.php', 'images.inc.php', 'header.php', 'footer.php', 'pdf-invoice.php'}
SOURCE_DEFAULT = Path(r'C:\Users\Utrh\Desktop\kafkaFTP\www\domains\kafka.monster')

def stable_read(path):
    try:
        before = path.stat()
        data = path.read_bytes()
        after = path.stat()
        if (before.st_size, before.st_mtime_ns) != (after.st_size, after.st_mtime_ns) or len(data) != after.st_size:
            return None
        return data
    except OSError:
        return None

def text(path):
    raw = stable_read(path)
    return raw.decode('utf-8', errors='replace') if raw is not None else None

def no_comments(code):
    return re.sub(r'/\*.*?\*/|(?m:^\s*//[^\n]*$)', '', code, flags=re.S)

def write_csv(path, rows, fields):
    with path.open('w', encoding='utf-8-sig', newline='') as f:
        writer = csv.DictWriter(f, fieldnames=fields, extrasaction='ignore')
        writer.writeheader()
        writer.writerows(rows)

def code_identity(code):
    return {
        'classes': re.findall(r'\bclass\s+(\w+)(?:\s+extends\s+(\w+))?', code),
        'methods': re.findall(r'\bfunction\s+(\w+)\s*\(', code),
    }

def parse_metadata(path):
    result = {'directory': path.name, 'entry_present': (path / (path.name + '.php')).exists()}
    xml = text(path / 'config.xml')
    if xml:
        try:
            tree = ET.fromstring(xml)
            for key in ['name', 'displayName', 'version', 'author', 'tab', 'is_configurable', 'need_instance']:
                value = tree.findtext(key)
                if value is not None:
                    result[key] = value.strip()
        except ET.ParseError:
            result['xml_status'] = 'incomplete_or_invalid'
    code = text(path / (path.name + '.php'))
    if code:
        for key in ['name', 'version', 'author', 'tab']:
            match = re.search(r'\$this->' + key + r'\s*=\s*[\x27\x22]([^\x27\x22]+)[\x27\x22]', code)
            if match:
                result['php_' + key] = match[1]
                if key == 'author' and '\ufffd' in match[1]:
                    raw = stable_read(path / (path.name + '.php'))
                    author_bytes = re.search(rb'\$this->author\s*=\s*[\x27\x22]([^\x27\x22]+)[\x27\x22]', raw) if raw else None
                    if author_bytes:
                        result['php_author'] = author_bytes[1].decode('latin-1')
                        result['php_author_encoding_note'] = 'Non-UTF8 public author field decoded as Latin-1; verify against vendor.'
        result['hooks_declared'] = sorted(set(re.findall(r'function\s+hook(\w+)\s*\(', code, re.I)))
        result['ps_compliance_declared'] = bool(re.search(r'\$this->ps_versions_compliancy', code))
    return result

def inventory(root):
    groups = defaultdict(lambda: {'files': 0, 'bytes': 0, 'zero_bytes': 0})
    entries, errors = {}, []
    for directory, dirs, files in os.walk(root, followlinks=False):
        dirs[:] = [d for d in dirs if not (Path(directory) / d).is_symlink()]
        for name in files:
            path = Path(directory) / name
            try:
                if path.is_symlink():
                    continue
                stat = path.stat()
            except OSError:
                errors.append('stat_failed')
                continue
            relative = path.relative_to(root).as_posix()
            group = relative.split('/')[0] if '/' in relative else '[root]'
            groups[group]['files'] += 1
            groups[group]['bytes'] += stat.st_size
            groups[group]['zero_bytes'] += stat.st_size == 0
            entries[relative] = (stat.st_size, stat.st_mtime_ns)
    return entries, dict(groups), errors

def compare_reference(root, entries, archive):
    result = {'status': 'no_reference', 'different': [], 'additional': [], 'missing': [], 'identical': 0, 'newline_only': 0}
    if not archive.exists():
        return result
    result['reference_sha256'] = hashlib.sha256(archive.read_bytes()).hexdigest()
    result['reference_url'] = 'https://github.com/PrestaShop/PrestaShop/tree/1.6.1.13'
    with zipfile.ZipFile(archive) as z:
        refs = {}
        for name in z.namelist():
            parts = name.split('/', 1)
            if len(parts) != 2:
                continue
            rel = parts[1]
            if rel.endswith('.php') and (rel.split('/')[0] in CORE_DIRS or rel in CORE_ROOT):
                refs[rel] = name
        result['reference_php_files'] = len(refs)
        for rel, name in refs.items():
            if rel not in entries:
                result['missing'].append(rel)
                continue
            local = stable_read(root / rel)
            if local is None:
                continue
            canonical = z.read(name)
            if local == canonical:
                result['identical'] += 1
            elif local.replace(b'\r\n', b'\n') == canonical.replace(b'\r\n', b'\n'):
                result['newline_only'] += 1
            else:
                result['different'].append({'path': rel, 'local_bytes': len(local), 'reference_bytes': len(canonical),
                    'local_sha256': hashlib.sha256(local).hexdigest(), 'reference_sha256': hashlib.sha256(canonical).hexdigest()})
        result['additional'] = sorted(rel for rel in entries if rel.endswith('.php') and rel.split('/')[0] in CORE_DIRS and rel not in refs and rel != 'config/settings.inc.php')
        result['status'] = 'partial_reference_comparison' if result['missing'] else 'reference_comparison_complete'
    return result

def sql_media(dump, root):
    # Minimal whitelist only; personal tables counted and discarded by the parser.
    from audit_dump import read_dump
    _, rows, counts = read_dump(dump, {
        'image': ['id_image', 'id_product'],
        'attachment': ['id_attachment', 'file', 'mime'],
        'product_attachment': ['id_product', 'id_attachment'],
        'product': ['id_product', 'active'],
        'image_type': ['name', 'width', 'height', 'products', 'categories', 'manufacturers', 'suppliers', 'stores'],
    })
    active = {r['id_product'] for r in rows['product'] if r['active'] == 1}
    missing = []
    found, missing_active = 0, 0
    for record in rows['image']:
        image = str(record['id_image'])
        # Both standard digit-folders and pre-move legacy storage are accepted.
        candidates = [Path('img/p') / '/'.join(image) / (image + '.jpg'),
                      Path('img/p') / (str(record['id_product']) + '-' + image + '.jpg')]
        hit = next((p for p in candidates if (root / p).is_file() and (root / p).stat().st_size > 0), None)
        if hit:
            found += 1
        else:
            is_active = record['id_product'] in active
            missing_active += is_active
            missing.append({'id_image': record['id_image'], 'id_product': record['id_product'],
                            'product_active': int(is_active), 'expected_path': candidates[0].as_posix(),
                            'status': 'not_downloaded_or_missing'})
    attachments = []
    for record in rows['attachment']:
        filename = str(record['file'])
        safe_name = bool(re.fullmatch(r'[A-Za-z0-9_.-]+', filename)) and filename not in {'.', '..'}
        relative = Path('download') / filename if safe_name else None
        # Export no file names: attachments may contain names or internal details.
        attachments.append({'id_attachment': record['id_attachment'], 'present_nonempty':
                            bool(relative and (root / relative).is_file() and (root / relative).stat().st_size > 0)})
    return {'expected_product_originals': len(rows['image']), 'present_product_originals': found,
            'pending_or_missing_product_originals': len(missing), 'pending_or_missing_active_image_rows': missing_active,
            'attachment_records': attachments, 'image_types': rows['image_type'], 'missing_images': missing}

def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--root', type=Path, default=SOURCE_DEFAULT)
    parser.add_argument('--out', type=Path, default=HERE / 'evidence')
    parser.add_argument('--media', action='store_true')
    parser.add_argument('--dump', type=Path, default=Path(r'C:\Users\Utrh\Downloads\d278806_rvujjz.sql'))
    args = parser.parse_args()
    args.out.mkdir(parents=True, exist_ok=True)
    entries, groups, errors = inventory(args.root)
    total = {'files': len(entries), 'bytes': sum(v[0] for v in entries.values()), 'groups': groups}
    stamp = datetime.now(timezone.utc).isoformat()
    previous_path = args.out / 'ftp-audit.json'
    previous = json.loads(previous_path.read_text(encoding='utf-8')) if previous_path.exists() else None
    report = {'observed_at_utc': stamp, 'root': str(args.root), 'download_state': 'unconfirmed_in_progress',
              'inventory': total, 'scan_errors': len(errors)}
    report['change_since_previous'] = {key: total[key] - previous['inventory'][key] for key in ['files', 'bytes']} if previous else None
    settings = text(args.root / 'config/settings.inc.php')
    defines = text(args.root / 'config/defines.inc.php')
    report['configuration'] = {'settings_present': settings is not None, 'defines_present': defines is not None}
    for content, allowed in [(settings, ['_PS_VERSION_', '_DB_PREFIX_', '_MYSQL_ENGINE_']),
                             (defines, ['_PS_MODE_DEV_', '_PS_DEBUG_SQL_', '_PS_CACHE_ENABLED_'])]:
        if content:
            for key in allowed:
                matches = re.findall(r'define\s*\(\s*[\x27\x22]' + re.escape(key) + r'[\x27\x22]\s*,\s*([^\n;]+)\)', content)
                if matches:
                    patterns = {'_PS_VERSION_': r'\d+(?:\.\d+){1,3}', '_DB_PREFIX_': r'[A-Za-z0-9_]{1,64}',
                                '_MYSQL_ENGINE_': r'(?i:innodb|myisam)', '_PS_MODE_DEV_': r'(?i:true|false|0|1)',
                                '_PS_DEBUG_SQL_': r'(?i:true|false|0|1)', '_PS_CACHE_ENABLED_': r'(?i:true|false|0|1)'}
                    values = list(dict.fromkeys(value.strip().strip('\x27\x22') for value in matches))
                    safe_values = [value if re.fullmatch(patterns[key], value) else 'dynamic_value_not_exported' for value in values]
                    report['configuration'][key] = safe_values[0] if len(values) == 1 else 'conditional_not_evaluated'
                    if len(values) > 1:
                        report['configuration'][key + '_literal_branches'] = safe_values
    report['configuration']['flags_interpretation'] = 'Static declarations only; runtime and externally predefined constants were not evaluated.'
    # Only record presence of credential defines, never their values.
    if settings:
        report['configuration']['credential_fields_present'] = [key for key in ['_DB_PASSWD_', '_COOKIE_KEY_', '_COOKIE_IV_', '_RIJNDAEL_KEY_'] if key in settings]
    operational_path = args.out / 'audit-summary.json'
    db = json.loads(operational_path.read_text(encoding='utf-8')) if operational_path.exists() else {}
    op = db.get('operational', {})
    db_modules = {r['name']: r for r in op.get('module', [])}
    assigned = {r['id_module'] for r in op.get('module_shop', [])}
    module_dir = args.root / 'modules'
    modules = []
    for name in sorted(set(db_modules) | ({p.name for p in module_dir.iterdir() if p.is_dir()} if module_dir.exists() else set())):
        path = module_dir / name
        metadata = parse_metadata(path) if path.is_dir() else {'directory': name, 'entry_present': False}
        record = db_modules.get(name)
        metadata.update(db_version=record['version'] if record else None,
                        db_assigned=record['id_module'] in assigned if record else False,
                        db_present=bool(record), directory_present=path.is_dir())
        modules.append(metadata)
    report['modules'] = modules
    report['module_counts'] = {'db_modules': len(db_modules), 'db_assigned': len(assigned),
        'local_directories': sum(m['directory_present'] for m in modules),
        'db_assigned_entry_present': sum(m['db_assigned'] and m['entry_present'] for m in modules)}
    overrides = []
    for rel in sorted(entries):
        if rel.startswith('override/') and rel.endswith('.php') and Path(rel).name != 'index.php':
            code = text(args.root / rel)
            if code is None:
                continue
            identity = code_identity(no_comments(code))
            overrides.append({'path': rel, 'bytes': len(code.encode('utf-8')), **identity,
                              'nonempty_override': bool(identity['classes'] or identity['methods'])})
    report['overrides'] = overrides
    class_index = text(args.root / 'cache/class_index.php')
    report['class_index_override_paths'] = sorted(set(re.findall(r'[\x27\x22](override/[^\x27\x22]+\.php)[\x27\x22]', class_index or '')))
    report['core'] = compare_reference(args.root, entries, args.out / 'prestashop-1.6.1.13-reference.zip')
    themes_dir = args.root / 'themes'
    report['themes'] = [{'directory': p.name, 'files': sum(rel.startswith('themes/' + p.name + '/') for rel in entries),
                         'config_xml_present': (p / 'config.xml').is_file()} for p in themes_dir.iterdir() if p.is_dir()] if themes_dir.exists() else []
    templates = []
    for rel in sorted(entries):
        if rel.startswith(('mails/', 'pdf/')) and Path(rel).suffix.lower() in {'.html', '.txt', '.tpl'}:
            templates.append(rel)
    report['mail_pdf_templates'] = templates
    sitemaps = []
    for rel in sorted(entries):
        if '/' not in rel and rel.endswith('sitemap.xml'):
            raw = stable_read(args.root / rel)
            if not raw:
                continue
            try:
                tree = ET.fromstring(raw)
                entries_xml = [e for e in tree if e.tag.rsplit('}', 1)[-1] in {'url', 'sitemap'}]
                urls = [e.text for item in entries_xml for e in item if e.tag.rsplit('}', 1)[-1] == 'loc' and e.text]
                image_urls = [e.text for item in entries_xml for image in item
                              if image.tag.rsplit('}', 1)[-1] == 'image' for e in image
                              if e.tag.rsplit('}', 1)[-1] == 'loc' and e.text]
                lastmod = [e.text for e in tree.iter() if e.tag.rsplit('}', 1)[-1] == 'lastmod' and e.text]
                product_ids = {int(m[1]) for u in urls if (m := re.search(r'/(\d+)-[^/]+\.html', u))}
                sitemaps.append({'file': rel, 'urls': len(urls), 'image_urls': len(image_urls), 'product_ids': len(product_ids),
                                 'latest_lastmod': max(lastmod) if lastmod else None,
                                 'url_schemes': dict(Counter(u.split(':')[0] for u in urls)),
                                 'root_element': tree.tag.rsplit('}', 1)[-1]})
            except ET.ParseError:
                sitemaps.append({'file': rel, 'status': 'incomplete_or_invalid'})
    report['sitemaps'] = sitemaps
    robots = text(args.root / 'robots.txt')
    report['robots'] = {'present': robots is not None,
                        'sitemap_directives': re.findall(r'(?mi)^Sitemap:\s*(https?://kafka\.monster/[^\s?]+)', robots or ''),
                        'blocks_entire_site': bool(re.search(r'(?mi)^Disallow:\s*/\s*$', robots or ''))}
    htaccess = text(args.root / '.htaccess')
    report['htaccess'] = {'present': htaccess is not None,
        'prestashop_generated': bool(htaccess and 'PrestaShop' in htaccess),
        'php_handler_directive': bool(re.search(r'(?mi)^\s*(?:AddHandler|SetHandler|AddType).*php', htaccess or '')),
        'rewrite_rules': len(re.findall(r'(?mi)^\s*RewriteRule\b', htaccess or '')),
        'redirect_directives': len(re.findall(r'(?mi)^\s*Redirect(?:Match)?\b', htaccess or '')),
        'contains_https': bool(htaccess and ('HTTPS' in htaccess or 'https://' in htaccess)),
        'autogenerated_markers': [line[:130] for line in (htaccess or '').splitlines() if line.startswith(('# ~~start~~', '# ~~end~~'))]}
    report['standalone_root_php'] = sorted(rel for rel in entries if '/' not in rel and rel.endswith('.php') and rel not in CORE_ROOT)
    report['expected_directories'] = {name: (args.root / name).is_dir() for name in
        ['classes', 'controllers', 'config', 'modules', 'themes', 'img', 'download', 'upload', 'override', 'mails', 'pdf', 'tools']}
    if args.media:
        media = sql_media(args.dump, args.root)
        missing = media.pop('missing_images')
        write_csv(args.out / 'ftp-pending-images.csv', missing,
                  ['id_image', 'id_product', 'product_active', 'expected_path', 'status'])
        report['media'] = media
    elif previous and 'media' in previous:
        report['media'] = previous['media']
        report['media_not_refreshed_in_this_scan'] = True
    previous_path.write_text(json.dumps(report, ensure_ascii=False, indent=2), encoding='utf-8')
    with (args.out / 'ftp-progress.jsonl').open('a', encoding='utf-8') as f:
        f.write(json.dumps({'observed_at_utc': stamp, 'files': total['files'], 'bytes': total['bytes'],
                            'groups': groups, 'change': report['change_since_previous']}, ensure_ascii=False) + '\n')
    write_csv(args.out / 'ftp-modules.csv', modules, ['directory', 'directory_present', 'entry_present', 'db_present',
        'db_assigned', 'db_version', 'php_version', 'version', 'php_author', 'author', 'ps_compliance_declared'])
    write_csv(args.out / 'ftp-overrides.csv', [{**r, 'classes': json.dumps(r['classes']), 'methods': ', '.join(r['methods'])} for r in overrides],
              ['path', 'bytes', 'nonempty_override', 'classes', 'methods'])
    print(json.dumps({'observed_at_utc': stamp, 'files': total['files'], 'bytes': total['bytes'],
        'change_since_previous': report['change_since_previous'], 'configuration': report['configuration'],
        'groups': groups, 'modules': report['module_counts'], 'overrides': len(overrides),
        'core_identical': report['core']['identical'], 'core_different': len(report['core']['different']),
        'core_pending': len(report['core']['missing']), 'media': report.get('media')}, ensure_ascii=False, indent=2))

if __name__ == '__main__':
    main()
