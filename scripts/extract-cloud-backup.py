"""Extract an authenticated cloud bundle only into its private workspace folder."""
import argparse
import tarfile
from pathlib import Path

p = argparse.ArgumentParser()
p.add_argument('--bundle', type=Path, required=True)
args = p.parse_args()
bundle = args.bundle.resolve(strict=True)
root = Path(__file__).resolve().parents[1] / 'local/backups'
if not bundle.is_relative_to(root.resolve()) or bundle.name != 'bundle.tar':
    raise ValueError('Cloud restore must stay inside local/backups')
with tarfile.open(bundle, 'r') as tar:
    # Validate the entire member set before extracting any files.
    members = tar.getmembers()
    seen = set()
    for member in members:
        destination = (bundle.parent / member.name).resolve()
        if not destination.is_relative_to(bundle.parent) or member.issym() or member.islnk() or member.isdev():
            raise ValueError('Unsafe cloud archive member')
        if destination.exists():
            raise ValueError('Refusing to overwrite an existing private restore file')
        if destination in seen:
            raise ValueError('Duplicate cloud archive member')
        seen.add(destination)
    tar.extractall(bundle.parent, members=members, filter='data')
print('Authenticated cloud backup extracted into a fresh private folder.')
