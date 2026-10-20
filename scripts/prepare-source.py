"""Archive original inputs, then prepare a private source clone. Never execute source PHP."""
import argparse
import hashlib
import json
import os
import re
import secrets
import shutil
import tarfile
from datetime import datetime
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
DIRECTORIES = {'Adapter','Core','classes','config','controllers','css','download','img','js','localization','mails','modules','override','pdf','themes','tools','translations','upload','webservice'}
ROOT_FILES = {'index.php','init.php','images.inc.php','header.php','footer.php','error500.html','LICENSES'}

def sha(path):
    h = hashlib.sha256()
    with path.open('rb') as f:
        for block in iter(lambda: f.read(1024*1024), b''):
            h.update(block)
    return h.hexdigest()

def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--ftp', type=Path, required=True)
    parser.add_argument('--sql', type=Path, required=True)
    args = parser.parse_args()
    ftp, sql = args.ftp.resolve(strict=True), args.sql.resolve(strict=True)
    stamp = datetime.now().strftime('%Y%m%d-%H%M%S')
    backup = ROOT / 'local/backups' / ('source-original-' + stamp)
    target = ROOT / 'local/source' / stamp
    backup.mkdir(parents=True, exist_ok=False)
    target.mkdir(parents=True, exist_ok=False)
    # Full original archive, including configuration and uploaded customer data, stays private.
    with tarfile.open(backup / 'source-files.tar.gz', 'w:gz', compresslevel=1) as archive:
        for base, dirs, files in os.walk(ftp, followlinks=False):
            for name in dirs + files:
                p = Path(base) / name
                if p.is_symlink() or p.is_junction():
                    raise RuntimeError('Source contains a link/junction; manual review required')
            for name in files:
                p = Path(base) / name
                archive.add(p, arcname=p.relative_to(ftp), recursive=False)
    shutil.copy2(sql, backup / 'database.sql')
    (backup / 'manifest.json').write_text(json.dumps({name: sha(backup/name) for name in ['source-files.tar.gz','database.sql']}, indent=2), encoding='utf-8')
    shop = target / 'shop'
    shop.mkdir()
    for entry in ftp.iterdir():
        if entry.is_dir() and (entry.name in DIRECTORIES or entry.name == 'guzg3arkfjazszdr'):
            shutil.copytree(entry, shop / entry.name)
        elif entry.is_file() and entry.name in ROOT_FILES:
            shutil.copy2(entry, shop / entry.name)
    # Fresh cache: never reuse production-generated class/config caches.
    for part in ['cache/smarty/cache','cache/smarty/compile','cache/cachefs','log']:
        (shop / part).mkdir(parents=True, exist_ok=True)
    settings_path = shop / 'config/settings.inc.php'
    settings = settings_path.read_text(encoding='utf-8')
    prefix = re.search(r"define\(['\"]_DB_PREFIX_['\"],\s*['\"]([A-Za-z0-9_]+)['\"]\)", settings)
    version = re.search(r"define\(['\"]_PS_VERSION_['\"],\s*['\"]([0-9.]+)['\"]\)", settings)
    if not prefix or not version or version[1] != '1.6.1.13':
        raise RuntimeError('Unexpected source version or table prefix')
    password = secrets.token_hex(24)
    for name, value in {'_DB_SERVER_':'db', '_DB_NAME_':'kafka_source', '_DB_USER_':'kafka_source', '_DB_PASSWD_':password, '_DB_PORT_':'', '_PS_CACHING_SYSTEM_':'CacheMemcache', '_PS_CACHE_ENABLED_':'0'}.items():
        pattern = r"define\(\s*['\"]" + name + r"['\"]\s*,\s*[^;]+\);"
        replacement = "define('" + name + "', '" + value + "');"
        settings, count = re.subn(pattern, lambda m: replacement, settings)
        if count == 0 and name in {'_DB_SERVER_','_DB_NAME_','_DB_USER_','_DB_PASSWD_'}:
            raise RuntimeError('Missing required database setting')
    settings_path.write_text(settings, encoding='utf-8')
    (shop / 'robots.txt').write_text('User-agent: *\nDisallow: /\n', encoding='ascii')
    # Strip USE/CREATE DATABASE statements; the supplied dump must only target the clone DB.
    raw = (backup / 'database.sql').read_text(encoding='utf-8-sig')
    raw = re.sub(r'(?mi)^\s*(?:CREATE DATABASE|USE\s+`)[^\n]*\n', '', raw)
    raw = raw.replace('NO_AUTO_CREATE_USER,', '').replace(',NO_AUTO_CREATE_USER', '')
    (target / 'database.sql').write_text(raw, encoding='utf-8')
    (target / '.env').write_text('SOURCE_DB_PASSWORD=' + password + '\nSOURCE_DB_ROOT_PASSWORD=' + secrets.token_hex(24) + '\nSOURCE_ADMIN_PASSWORD=' + secrets.token_hex(16) + '\n', encoding='ascii')
    state = {'path':str(target), 'original_backup':str(backup), 'source_sql_date':datetime.fromtimestamp(sql.stat().st_mtime).isoformat(), 'version':version[1], 'prefix':prefix[1], 'status':'prepared-not-started', 'url':'http://localhost:8082/'}
    (ROOT / 'local/source-state.json').write_text(json.dumps(state, indent=2), encoding='utf-8')
    print(json.dumps({'status':state['status'], 'version':state['version'], 'private_backup':str(backup), 'clone':str(target)}))

if __name__ == '__main__':
    main()
