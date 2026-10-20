"""Decode only numeric product-original JPEGs from the read-only FTP copy.

Does not execute source code or inspect logs, sessions or customer uploads.
Results are provisional while the FTP client is still writing files.
"""
import io
import json
import re
from datetime import datetime, timezone
from pathlib import Path

from PIL import Image
from audit_ftp import SOURCE_DEFAULT, stable_read

HERE = Path(__file__).resolve().parent

def decode_originals(directory):
    result = {'candidates': 0, 'decoded': 0, 'unstable_or_unreadable': 0,
              'empty': 0, 'decode_failures': []}
    for path in directory.rglob('*.jpg'):
        if not re.fullmatch(r'\d+\.jpg', path.name):
            continue
        result['candidates'] += 1
        raw = stable_read(path)
        if raw is None:
            result['unstable_or_unreadable'] += 1
            continue
        if not raw:
            result['empty'] += 1
            continue
        try:
            with Image.open(io.BytesIO(raw)) as picture:
                picture.verify()
            with Image.open(io.BytesIO(raw)) as picture:
                picture.load()
            result['decoded'] += 1
        except Exception as error:
            result['decode_failures'].append({'numeric_file_id': int(path.stem),
                'error_type': type(error).__name__, 'status': 'provisional_recheck_after_transfer'})
    return result

def main():
    result = {'observed_at_utc': datetime.now(timezone.utc).isoformat(),
              'scope': 'img/p numeric original JPEG filenames only; no thumbnails or customer uploads',
              'download_completion_confirmed': False,
              **decode_originals(SOURCE_DEFAULT / 'img/p')}
    result['nonproduct_media'] = {}
    for folder, purpose in [('c', 'categories'), ('m', 'manufacturers'), ('su', 'suppliers'), ('st', 'stores')]:
        directory = SOURCE_DEFAULT / 'img' / folder
        result['nonproduct_media'][purpose] = {'directory_present': directory.is_dir(),
            'scope': 'numeric original JPG filenames only; images may be optional, no database completeness claim',
            **decode_originals(directory)}
    target = HERE / 'evidence/media-integrity.json'
    target.write_text(json.dumps(result, ensure_ascii=False, indent=2), encoding='utf-8')
    print(json.dumps(result, ensure_ascii=False))

if __name__ == '__main__':
    main()
