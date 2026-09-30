#!/usr/bin/env python3
"""Read-only hash verification of the documented production source scope."""
import argparse
import hashlib
import json
from pathlib import Path

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--live-app', type=Path)
parser.add_argument('--live-webroot', type=Path)
args = parser.parse_args()
if bool(args.live_app) != bool(args.live_webroot):
    parser.error('supply both live paths, or neither to verify the repository')
repo = Path(__file__).resolve().parents[1]
manifest = json.loads((repo / 'docs/operations/production-source-manifest-20260930.json').read_text())
failures = []
for row in manifest['files']:
    rel = Path(row['path'])
    if rel.is_absolute() or '..' in rel.parts:
        raise ValueError('unsafe manifest path')
    target = repo / rel
    if args.live_app:
        if rel.parts[0] == 'backend':
            target = args.live_app.joinpath(*rel.parts[1:])
        elif rel.parts[:2] == ('deployment', 'webroot'):
            target = args.live_webroot.joinpath(*rel.parts[2:])
        else:
            raise ValueError('unsupported source scope')
    if target.is_symlink() or not target.is_file():
        failures.append({'path': str(rel), 'reason': 'missing or symlink'})
    elif hashlib.sha256(target.read_bytes()).hexdigest() != row['sha256']:
        failures.append({'path': str(rel), 'reason': 'hash mismatch'})
print(json.dumps({'checked': len(manifest['files']), 'failures': failures}, indent=2))
raise SystemExit(1 if failures else 0)
