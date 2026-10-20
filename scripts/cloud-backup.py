"""Streaming AES-256-GCM backup package; recovery key is NEVER part of the upload."""
import argparse
import base64
import hashlib
import json
import os
import re
import shutil
import tarfile
from datetime import datetime
from pathlib import Path
from cryptography.hazmat.primitives.ciphers import Cipher, algorithms, modes

ROOT = Path(__file__).resolve().parents[1]
MAGIC = b'KAFKA-BACKUP-GCM1\n'
CHUNK = 1024 * 1024

def digest(path):
    h = hashlib.sha256()
    with path.open('rb') as f:
        for block in iter(lambda: f.read(CHUNK), b''):
            h.update(block)
    return h.hexdigest()

def verify(archive, key, output=None):
    with archive.open('rb') as f:
        if f.read(len(MAGIC)) != MAGIC:
            raise ValueError('Unknown backup format')
        nonce = f.read(12)
        size = archive.stat().st_size - len(MAGIC) - 12 - 16
        if size < 0:
            raise ValueError('Truncated backup')
        f.seek(-16, 2)
        decryptor = Cipher(algorithms.AES(key), modes.GCM(nonce, f.read(16))).decryptor()
        decryptor.authenticate_additional_data(MAGIC)
        f.seek(len(MAGIC) + 12)
        h = hashlib.sha256()
        while size:
            chunk = f.read(min(CHUNK, size))
            if not chunk:
                raise ValueError('Truncated backup')
            size -= len(chunk)
            plain = decryptor.update(chunk)
            h.update(plain)
            if output:
                output.write(plain)
        tail = decryptor.finalize()  # fails if any byte or authentication tag was modified
        h.update(tail)
        if output:
            output.write(tail)
        return h.hexdigest()

def main():
    p = argparse.ArgumentParser()
    p.add_argument('--backup', type=Path, action='append', default=[])
    p.add_argument('--verify', type=Path)
    p.add_argument('--key-file', type=Path)
    p.add_argument('--decrypt-to', type=Path)
    p.add_argument('--label', help='Private package label, e.g. 20261014-final')
    args = p.parse_args()
    if args.verify:
        if not args.key_file:
            p.error('--key-file is required for verification')
        key = base64.b64decode(args.key_file.read_bytes().strip(), validate=True)
        if args.decrypt_to:
            output = args.decrypt_to.resolve()
            if not output.is_relative_to((ROOT / 'local/backups').resolve()):
                raise ValueError('Plaintext restore must stay in local/backups')
            if output.exists():
                raise ValueError('Refusing to overwrite existing restore file')
            try:
                with output.open('xb') as f:
                    sha = verify(args.verify, key, f)
            except Exception:
                output.unlink(missing_ok=True)
                raise
        else:
            sha = verify(args.verify, key)
        manifest = json.loads(args.verify.with_suffix('.json').read_text(encoding='utf-8'))
        if sha != manifest['plaintext_sha256'] or digest(args.verify) != manifest['encrypted_sha256']:
            if args.decrypt_to:
                output.unlink(missing_ok=True)
            raise ValueError('Backup digest mismatch')
        print('Backup authentication and decrypted checksum verified.')
        return
    if not args.backup:
        p.error('Specify at least one --backup folder')
    backups = [x.resolve(strict=True) for x in args.backup]
    for folder in backups:
        if not folder.is_relative_to((ROOT / 'local/backups').resolve()) or not folder.is_dir():
            raise ValueError('Only private backup folders can be packaged')
    if len({x.name for x in backups}) != len(backups):
        raise ValueError('Backup folder names must be unique')
    required = sum(x.stat().st_size for folder in backups for x in folder.rglob('*') if x.is_file()) + 512*1024*1024
    if shutil.disk_usage(ROOT).free < required:
        raise ValueError('Insufficient free space for an encrypted backup plus reserve; existing backups were preserved')
    stamp = args.label or datetime.now().strftime('%Y%m%d-%H%M%S')
    if not re.fullmatch(r'\d{8}-[a-z0-9-]+', stamp):
        raise ValueError('Invalid backup label')
    target = ROOT / 'local/backups' / ('cloud-ready-' + stamp)
    target.mkdir(exist_ok=False)
    private = ROOT / 'local/private'
    private.mkdir(exist_ok=True)
    keyfile = private / ('cloud-recovery-' + stamp + '.key')
    key = os.urandom(32)
    with keyfile.open('xb') as f:
        f.write(base64.b64encode(key) + b'\n')
    archive = target / 'kafka-backup.kafkagcm'
    nonce = os.urandom(12)
    encryptor = Cipher(algorithms.AES(key), modes.GCM(nonce)).encryptor()
    encryptor.authenticate_additional_data(MAGIC)
    plaintext_hash = hashlib.sha256()
    class EncryptedWriter:
        def __init__(self, stream):
            self.stream = stream
        def write(self, data):
            plaintext_hash.update(data)
            self.stream.write(encryptor.update(data))
            return len(data)
        def flush(self):
            self.stream.flush()
    with archive.open('xb') as dest:
        dest.write(MAGIC + nonce)
        # Stream tar directly into encryption; no second multi-GB plaintext bundle.
        with tarfile.open(fileobj=EncryptedWriter(dest), mode='w|') as tar:
            for folder in backups:
                tar.add(folder, arcname=folder.name)
        dest.write(encryptor.finalize())
        dest.write(encryptor.tag)
    expected = plaintext_hash.hexdigest()
    if verify(archive, key) != expected:
        raise ValueError('Encryption round trip failed')
    metadata = {'format':'AES-256-GCM / KAFKA-BACKUP-GCM1', 'plaintext_sha256':expected, 'encrypted_sha256':digest(archive), 'bytes':archive.stat().st_size, 'backups':[x.name for x in backups], 'local_decryption_verified':True, 'cloud_uploaded':False, 'cloud_download_verified':False}
    archive.with_suffix('.json').write_text(json.dumps(metadata, indent=2), encoding='utf-8')
    print(json.dumps({'encrypted_archive':str(archive), 'recovery_key_file':str(keyfile), 'verified':True, 'upload_pending':True}))

if __name__ == '__main__':
    main()
