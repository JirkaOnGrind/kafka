"""Read-only structural checks of opaque attachment filenames; no content export."""
import io
import json
import logging
import re
from datetime import datetime, timezone
from pathlib import Path

from PIL import Image
from pypdf import PdfReader
from audit_ftp import SOURCE_DEFAULT, stable_read

def main():
    logging.getLogger('pypdf').setLevel(logging.CRITICAL)
    result = {'observed_at_utc': datetime.now(timezone.utc).isoformat(),
        'scope': 'download filenames consisting of 40 hexadecimal characters only; no content or filenames exported',
        'files': 0, 'formats': {}, 'readable_formats': 0, 'unknown_formats': 0,
        'unstable_or_empty': 0, 'validation_failures': 0, 'download_completion_confirmed': False}
    for path in (SOURCE_DEFAULT / 'download').iterdir():
        if not path.is_file() or not re.fullmatch(r'[a-fA-F0-9]{40}', path.name):
            continue
        result['files'] += 1
        raw = stable_read(path)
        if not raw:
            result['unstable_or_empty'] += 1
            continue
        if raw.startswith(b'%PDF-'):
            kind = 'PDF'
        elif raw.startswith((b'\xff\xd8\xff', b'\x89PNG\r\n\x1a\n', b'GIF87a', b'GIF89a')):
            kind = 'raster_image'
        else:
            kind = 'unclassified'
        result['formats'][kind] = result['formats'].get(kind, 0) + 1
        try:
            if kind == 'PDF':
                if len(PdfReader(io.BytesIO(raw), strict=False).pages) < 1:
                    raise ValueError('No pages')
                result['readable_formats'] += 1
            elif kind == 'raster_image':
                with Image.open(io.BytesIO(raw)) as picture:
                    picture.verify()
                with Image.open(io.BytesIO(raw)) as picture:
                    picture.load()
                result['readable_formats'] += 1
            else:
                result['unknown_formats'] += 1
        except Exception:
            result['validation_failures'] += 1
    target = Path(__file__).resolve().parent / 'evidence/attachment-format.json'
    target.write_text(json.dumps(result, ensure_ascii=False, indent=2), encoding='utf-8')
    print(json.dumps(result, ensure_ascii=False))

if __name__ == '__main__':
    main()
