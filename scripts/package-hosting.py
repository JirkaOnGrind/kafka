"""Build a locked FTP tree from a private demo-shop export. No network or DB access."""
import hashlib
import json
import re
import shutil
import tarfile
from pathlib import Path, PurePosixPath

ROOT = Path(__file__).resolve().parents[1]
BASE = ROOT / 'local/packages/hosting-20261018'
PRIVATE = BASE / 'private'
UPLOAD = BASE / 'upload'
if UPLOAD.exists():
    raise RuntimeError('Refusing to overwrite an existing upload tree')
UPLOAD.mkdir()
excluded = []
count = 0
with tarfile.open(PRIVATE / 'shopfiles-source.tar.gz', 'r:gz') as archive:
    for member in archive:
        path = PurePosixPath(member.name)
        if path.is_absolute() or '..' in path.parts:
            raise RuntimeError('Unsafe archive path')
        name = str(path)
        if name == '.':
            continue
        parts = path.parts
        if (any(p in ('.git', '.svn', 'node_modules') or p.startswith('.env') for p in parts)
                or name.startswith(('var/cache/', 'var/log/', 'var/logs/', 'install/', 'install-kafka/'))
                or name in ('app/config/parameters.php', 'app/config/parameters.yml')
                or name.endswith(('.sql', '.sql.gz', '.log'))):
            excluded.append(name)
            continue
        if member.isdir():
            continue
        if member.issym() and name in ('admin-dev/bundles/apiplatform', 'admin-dev/bundles/fosjsrouting', 'admin-kafka'):
            excluded.append(name)
            continue
        if not member.isfile():
            raise RuntimeError('Non-regular archive entry: ' + name)
        data = archive.extractfile(member).read()
        if name == '.htaccess':
            data = data.replace(b'localhost:8080', b'kafka.jirkanetrh.cz')
            data = (b'# KAFKA TEST LOCK: keep until access protection is configured.\n'
                    b'Require all denied\nOptions -Indexes\n'
                    b'<IfModule mod_headers.c>\nHeader always set X-Robots-Tag "noindex, nofollow, noarchive"\n</IfModule>\n' + data)
        if name == 'config/defines.inc.php':
            data = re.sub(rb"define\('_PS_MODE_DEV_',\s*true\)", b"define('_PS_MODE_DEV_', false)", data)
        target = UPLOAD.joinpath(*parts)
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_bytes(data)
        count += 1
if not (UPLOAD / '.htaccess').is_file():
    raise RuntimeError('Source .htaccess is missing; do not upload')
(UPLOAD / 'app/config/parameters.php').write_bytes((PRIVATE / 'parameters.php').read_bytes())
# FTP cannot preserve the absolute Docker symlinks used by these admin assets.
for target, source in {
    'admin-dev/bundles/apiplatform': 'vendor/api-platform/core/src/Symfony/Bundle/Resources/public',
    'admin-dev/bundles/fosjsrouting': 'vendor/friendsofsymfony/jsrouting-bundle/Resources/public',
}.items():
    shutil.copytree(UPLOAD / source, UPLOAD / target)
# Current project sources override copies in Docker's snapshot.
for folder in ['themes/kafka', 'themes/hummingbird', 'modules/kafkahome']:
    for source in (ROOT / 'prestashop' / folder).rglob('*'):
        if not source.is_file():
            continue
        relative = source.relative_to(ROOT / 'prestashop')
        if 'cache' in relative.parts or any(p.startswith('.env') or p == '.git' for p in relative.parts):
            continue
        dest = UPLOAD / relative
        dest.parent.mkdir(parents=True, exist_ok=True)
        dest.write_bytes(source.read_bytes())

sql = (PRIVATE / 'database.sql').read_bytes()
tail = (PRIVATE / 'target-settings.sql').read_bytes()
if b'CREATE DATABASE' in sql or re.search(rb'^USE\s', sql, re.M):
    raise RuntimeError('SQL must import only into the database selected in phpMyAdmin')
# MariaDB older than 11.4 cannot read MySQL 8's default collation.
sql = sql.replace(b'utf8mb4_0900_ai_ci', b'utf8mb4_unicode_ci')
(PRIVATE / 'database-hosting.sql').write_bytes(sql + tail)
manifest = {'domain': 'kafka.jirkanetrh.cz', 'source_version': '9.1.5',
            'demo_products': 19, 'locked': True, 'database_tested': False,
            'hosting_tested': False, 'source_files': count, 'excluded_files': len(excluded),
            'sql_sha256': hashlib.sha256(sql + tail).hexdigest()}
(BASE / 'manifest.json').write_text(json.dumps(manifest, indent=2), encoding='utf-8')
print(json.dumps(manifest))
