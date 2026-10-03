#!/usr/bin/env python3
"""Deploy a reviewed source-only release; default is a read-only dry run."""
import argparse
import datetime
import fcntl
import hashlib
import json
import os
import pathlib
import shutil
import stat
import subprocess
import tempfile

APP = pathlib.Path('/home/royadarman/apps/royadarman-backend')
WEB = pathlib.Path('/home/royadarman/public_html')
PHP = '/opt/cpanel/ea-php83/root/usr/bin/php'
PREFIXES = ('backend/app/', 'backend/lang/', 'backend/resources/views/',
            'backend/public/assets/', 'deployment/webroot/assets/')


def digest(path):
    return hashlib.sha256(path.read_bytes()).hexdigest() if path.is_file() else None


def target(name):
    relative = pathlib.PurePosixPath(name)
    if relative.is_absolute() or '..' in relative.parts:
        raise RuntimeError('Unsafe path')
    if name.startswith('backend/'):
        return APP / name.removeprefix('backend/')
    if name.startswith('deployment/webroot/'):
        return WEB / name.removeprefix('deployment/webroot/')
    raise RuntimeError('Unexpected source namespace')


def replace(source, destination, mode):
    destination.parent.mkdir(parents=True, exist_ok=True)
    fd, temporary = tempfile.mkstemp(prefix='.codex-release-', dir=destination.parent)
    try:
        with os.fdopen(fd, 'wb') as stream:
            stream.write(source.read_bytes())
            stream.flush()
            os.fsync(stream.fileno())
        os.chmod(temporary, mode)
        os.replace(temporary, destination)
    finally:
        if os.path.exists(temporary):
            os.unlink(temporary)


def clear_caches():
    result = subprocess.run([PHP, 'artisan', 'optimize:clear'], cwd=APP,
                            capture_output=True, text=True, timeout=60)
    if result.returncode:
        raise RuntimeError('Cache clear failed')


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--source', required=True)
    parser.add_argument('--baseline', default='docs/operations/2026-10-03-live-reconciliation.json')
    parser.add_argument('--apply', action='store_true')
    args = parser.parse_args()
    source = pathlib.Path(args.source).resolve()
    baseline_path = (source / args.baseline).resolve()
    if not baseline_path.is_relative_to(source):
        raise RuntimeError('Baseline must be inside the reviewed source checkout')
    baseline = json.loads(baseline_path.read_text())['files']
    commit = subprocess.check_output(['git', '-C', str(source), 'rev-parse', 'HEAD'], text=True).strip()
    dirty = subprocess.check_output(['git', '-C', str(source), 'status', '--porcelain', '--untracked-files=no'], text=True)
    if dirty:
        raise RuntimeError('Tracked source is dirty')
    if args.apply and os.getuid() != 1001:
        raise RuntimeError('Apply as royadarman, never as root')
    names = subprocess.check_output(['git', '-C', str(source), 'ls-files', '-z'], text=True).split('\0')
    candidates = [name for name in names if name.startswith(PREFIXES)]
    with open('/home/royadarman/apps/.royadarman-deploy.lock', 'a+') as lock:
        fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        drift = [name for name, expected in baseline.items() if digest(target(name)) != expected]
        if drift:
            print(json.dumps({'error': 'live_drift', 'paths': drift}))
            return 1
        changes = []
        for name in candidates:
            destination = target(name)
            if destination.is_symlink() or destination.resolve() != destination:
                raise RuntimeError('Symlink in destination')
            if name not in baseline and destination.exists():
                raise RuntimeError('Unrecorded existing destination')
            if digest(source / name) != digest(destination):
                changes.append(name)
        if not args.apply:
            print(json.dumps({'commit': commit, 'dry_run': True, 'changed_files': changes}))
            return 0
        stamp = datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%SZ')
        # Older root-owned archives retain their existing private permissions.
        backup_root = pathlib.Path('/home/royadarman/royadarman-source-backups')
        backup_root.mkdir(mode=0o700, exist_ok=True)
        os.chmod(backup_root, 0o700)
        backup = backup_root / ('codex-source-' + stamp)
        backup.mkdir(mode=0o700, exist_ok=False)
        records, written = {}, []
        for name in changes:
            destination = target(name)
            mode = stat.S_IMODE(destination.stat().st_mode) if destination.exists() else 0o644
            records[name] = {'before': digest(destination), 'after': digest(source / name), 'mode': mode}
            if destination.exists():
                saved = backup / name
                saved.parent.mkdir(parents=True, exist_ok=True)
                shutil.copyfile(destination, saved)
                os.chmod(saved, 0o600)
                if digest(saved) != records[name]['before']:
                    raise RuntimeError('Backup integrity mismatch')
        record_path = backup / 'release.json'
        record_path.write_text(json.dumps({'commit': commit, 'files': records, 'state': 'backed_up'}, indent=2))
        os.chmod(record_path, 0o600)
        try:
            for name in changes:
                replace(source / name, target(name), records[name]['mode'])
                written.append(name)
            clear_caches()
            checks = {}
            for url, expected in [('https://royadarman.com/fa/login', '200'),
                                  ('https://royadarman.com/fa/panel', '302'),
                                  ('https://royadarman.com/assets/patient-request.css?v=20261003', '200'),
                                  ('https://royadarman.com/assets/profile-workspace.css?v=20261003', '200'),
                                  ('https://royadarman.com/assets/profile-workspace.js?v=20261003', '200'),
                                  ('https://royadarman.com/studio/', '200')]:
                result = subprocess.run(['curl', '-sS', '-o', '/dev/null', '-w', '%{http_code}',
                                         '--max-time', '15', url], capture_output=True, text=True)
                checks[url] = result.stdout
                if result.returncode or result.stdout != expected:
                    raise RuntimeError('HTTP verification failed')
            if any(digest(target(name)) != record['after'] for name, record in records.items()):
                raise RuntimeError('Post-deployment hash mismatch')
            record_path.write_text(json.dumps({'commit': commit, 'files': records, 'state': 'deployed',
                                              'checks': checks}, indent=2))
            print(json.dumps({'commit': commit, 'backup': str(backup), 'state': 'deployed',
                              'files': len(changes), 'checks': checks}))
        except Exception as error:
            for name in reversed(written):
                if records[name]['before'] is None:
                    target(name).unlink()
                else:
                    replace(backup / name, target(name), records[name]['mode'])
            if any(digest(target(name)) != records[name]['before'] for name in written):
                raise RuntimeError('Rollback hash mismatch')
            clear_caches()
            record_path.write_text(json.dumps({'commit': commit, 'files': records, 'state': 'rolled_back',
                                              'error_class': type(error).__name__}, indent=2))
            print(json.dumps({'state': 'rolled_back', 'backup': str(backup), 'error_class': type(error).__name__}))
            return 1
        return 0


if __name__ == '__main__':
    raise SystemExit(main())
