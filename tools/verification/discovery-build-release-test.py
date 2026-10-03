"""Synthetic filesystem checks; never calls production deployment or services."""
import importlib.util
import json
import pathlib
import tempfile
import unittest
from unittest.mock import patch
import contextlib
import io
import sys
import types

spec = importlib.util.spec_from_file_location('release', pathlib.Path(__file__).parents[1] / 'deploy_source_release.py')
release = importlib.util.module_from_spec(spec)
spec.loader.exec_module(release)


class DiscoveryBuildReleaseTest(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory()
        self.addCleanup(self.temporary.cleanup)
        self.source = pathlib.Path(self.temporary.name).resolve()
        self.manifest = {
            'resources/js/app.js': {'file': 'assets/app-kept.js', 'isEntry': True},
            release.DISCOVERY_ENTRY: {
                'file': 'assets/discovery-map-fixed.js', 'isEntry': True,
                'src': release.DISCOVERY_ENTRY, 'css': ['assets/discovery-map-fixed.css'],
            },
        }
        for root in release.BUILD_ROOTS:
            path = self.source / root
            (path / 'assets').mkdir(parents=True)
            for name in ['discovery-map-fixed.js', 'discovery-map-fixed.css', 'discovery-map-old.js']:
                (path / 'assets' / name).write_text(name)
        self.write_manifests()
        self.live = self.source / 'live-manifest.json'
        self.live.write_text(json.dumps(self.manifest))

    def write_manifests(self):
        for root in release.BUILD_ROOTS:
            (self.source / root / 'manifest.json').write_text(json.dumps(self.manifest))

    def verify(self):
        return release.discovery_build_paths(self.source, self.live)

    def test_only_current_map_dependencies_selected_old_assets_preserved(self):
        selected = self.verify()
        self.assertEqual(len(selected), 6)
        self.assertFalse(any('app-kept' in name or 'old.js' in name for name in selected))
        for root in release.BUILD_ROOTS:
            self.assertEqual((self.source / root / 'assets/discovery-map-old.js').read_text(), 'discovery-map-old.js')

    def test_unrelated_entry_change_refused(self):
        self.manifest['resources/js/app.js']['file'] = 'assets/app-unreviewed.js'
        self.write_manifests()
        with self.assertRaisesRegex(RuntimeError, 'Unrelated build entry'):
            self.verify()

    def test_new_unrelated_entry_refused(self):
        self.manifest['surprise'] = {'file': 'assets/new.js'}
        self.write_manifests()
        with self.assertRaisesRegex(RuntimeError, 'Unrelated build entry'):
            self.verify()

    def test_manifest_mirror_mismatch_refused(self):
        (self.source / release.BUILD_ROOTS[1] / 'manifest.json').write_text('{}')
        with self.assertRaisesRegex(RuntimeError, 'manifest mirrors'):
            self.verify()

    def test_artifact_mirror_mismatch_refused(self):
        (self.source / release.BUILD_ROOTS[1] / 'assets/discovery-map-fixed.js').write_text('different')
        with self.assertRaisesRegex(RuntimeError, 'artifact mirrors'):
            self.verify()

    def test_missing_artifact_refused(self):
        (self.source / release.BUILD_ROOTS[0] / 'assets/discovery-map-fixed.css').unlink()
        with self.assertRaisesRegex(RuntimeError, 'Invalid discovery artifact'):
            self.verify()

    def test_symlink_artifact_refused(self):
        asset = self.source / release.BUILD_ROOTS[0] / 'assets/discovery-map-fixed.js'
        asset.unlink()
        asset.symlink_to(self.live)
        with self.assertRaisesRegex(RuntimeError, 'Invalid discovery artifact'):
            self.verify()

    def test_symlinked_manifests_refused(self):
        for root in release.BUILD_ROOTS:
            path = self.source / root / 'manifest.json'
            path.unlink()
            path.symlink_to(self.live)
        with self.assertRaisesRegex(RuntimeError, 'Invalid build manifest file'):
            self.verify()

    def test_existing_asset_name_cannot_change_bytes(self):
        (self.live.parent / 'assets').mkdir()
        (self.live.parent / 'assets/discovery-map-fixed.js').write_text('old live bytes')
        with self.assertRaisesRegex(RuntimeError, 'immutable'):
            self.verify()

    def test_unreviewed_shared_chunk_refused(self):
        for key in ['imports', 'dynamicImports', 'assets']:
            with self.subTest(key=key):
                self.manifest[release.DISCOVERY_ENTRY][key] = ['assets/shared.js']
                self.write_manifests()
                with self.assertRaisesRegex(RuntimeError, 'self-contained'):
                    self.verify()
                del self.manifest[release.DISCOVERY_ENTRY][key]

    def test_unsafe_or_unrelated_artifact_path_refused(self):
        for name in ['../../.env', '/etc/passwd', 'assets/app-kept.js', 'assets/discovery-map-x.js?x=1']:
            with self.subTest(name=name):
                self.manifest[release.DISCOVERY_ENTRY]['file'] = name
                self.write_manifests()
                with self.assertRaisesRegex(RuntimeError, 'artifact path'):
                    self.verify()

    def test_javascript_cannot_be_css(self):
        self.manifest[release.DISCOVERY_ENTRY]['css'] = ['assets/discovery-map-fixed.js']
        self.write_manifests()
        with self.assertRaisesRegex(RuntimeError, 'artifact path'):
            self.verify()

    def test_non_entry_manifest_refused(self):
        self.manifest[release.DISCOVERY_ENTRY]['isEntry'] = False
        self.write_manifests()
        with self.assertRaisesRegex(RuntimeError, 'Missing discovery entry'):
            self.verify()


class DiscoveryDeploymentLifecycleTest(unittest.TestCase):
    """Run real atomic file writes and backup/rollback using only temporary trees."""
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory()
        self.addCleanup(self.temporary.cleanup)
        root = pathlib.Path(self.temporary.name).resolve()
        self.source, self.app, self.web = root / 'source', root / 'app', root / 'web'
        self.source.mkdir()
        self.patches = [
            patch.object(release, 'APP', self.app), patch.object(release, 'WEB', self.web),
            patch.object(release, 'LOCK', root / 'deploy.lock'),
            patch.object(release, 'BACKUP_ROOT', root / 'backups'),
            patch.object(release.os, 'getuid', return_value=1001),
        ]
        for item in self.patches:
            item.start()
            self.addCleanup(item.stop)
        old = {release.DISCOVERY_ENTRY: {'file': 'assets/discovery-map-old.js', 'isEntry': True,
                'src': release.DISCOVERY_ENTRY, 'css': ['assets/discovery-map-old.css']},
               'resources/js/app.js': {'file': 'assets/app-preserved.js'}}
        new = {**old, release.DISCOVERY_ENTRY: {**old[release.DISCOVERY_ENTRY],
               'file': 'assets/discovery-map-new.js', 'css': ['assets/discovery-map-new.css']}}
        self.baseline = {}
        for prefix in release.BUILD_ROOTS:
            for name, content in {'manifest.json': json.dumps(old),
                                  'assets/discovery-map-old.js': 'old JS',
                                  'assets/discovery-map-old.css': 'old CSS'}.items():
                path = release.target(prefix + name)
                path.parent.mkdir(parents=True, exist_ok=True)
                path.write_text(content)
                self.baseline[prefix + name] = release.digest(path)
                source_path = self.source / prefix / name
                source_path.parent.mkdir(parents=True, exist_ok=True)
                source_path.write_text(content)
            (self.source / prefix / 'manifest.json').write_text(json.dumps(new))
            (self.source / prefix / 'assets/discovery-map-new.js').write_text('new JS')
            (self.source / prefix / 'assets/discovery-map-new.css').write_text('new CSS')
        name = 'backend/resources/js/discovery-map.js'
        destination = release.target(name)
        destination.parent.mkdir(parents=True)
        destination.write_text('old source')
        self.baseline[name] = release.digest(destination)
        (self.source / name).parent.mkdir(parents=True)
        (self.source / name).write_text('new source')
        (self.source / 'baseline.json').write_text(json.dumps({'files': self.baseline}))
        self.names = [str(path.relative_to(self.source)) for path in self.source.rglob('*') if path.is_file()]

    def invoke(self, fail_http=False):
        def git_output(command, **kwargs):
            return 'synthetic-commit\n' if 'rev-parse' in command else ('' if 'status' in command else '\0'.join(self.names))
        def http(command, **kwargs):
            url = command[-1]
            status = '503' if fail_http else ('302' if url.endswith('/panel') else '200')
            return types.SimpleNamespace(returncode=0, stdout=status)
        with patch.object(sys, 'argv', ['deploy', '--source', str(self.source), '--baseline', 'baseline.json', '--apply']), \
                patch.object(release.subprocess, 'check_output', side_effect=git_output), \
                patch.object(release.subprocess, 'run', side_effect=http), \
                patch.object(release, 'clear_caches') as caches, \
                patch.object(release, 'replace', wraps=release.replace) as writes, \
                contextlib.redirect_stdout(io.StringIO()):
            result = release.main()
            return result, writes.call_args_list, caches.call_count

    def test_apply_assets_before_manifests_old_assets_unchanged(self):
        result, writes, caches = self.invoke()
        self.assertEqual((result, caches), (0, 1))
        destinations = [call.args[1] for call in writes]
        manifest_indexes = [i for i, path in enumerate(destinations) if path.name == 'manifest.json']
        asset_indexes = [i for i, path in enumerate(destinations) if path.suffix in ('.js', '.css') and 'assets' in path.parts]
        self.assertLess(max(asset_indexes), min(manifest_indexes))
        for name, digest in self.baseline.items():
            if '/assets/' in name:
                self.assertEqual(release.digest(release.target(name)), digest)
        for prefix in release.BUILD_ROOTS:
            self.assertEqual((release.target(prefix + 'manifest.json')).read_bytes(), (self.source / prefix / 'manifest.json').read_bytes())
        record = json.loads(next(release.BACKUP_ROOT.glob('*/release.json')).read_text())
        self.assertEqual(record['state'], 'deployed')

    def test_http_failure_restores_every_old_byte_removes_new_assets(self):
        result, writes, caches = self.invoke(fail_http=True)
        self.assertEqual((result, caches), (1, 2))
        for name, digest in self.baseline.items():
            self.assertEqual(release.digest(release.target(name)), digest)
        for prefix in release.BUILD_ROOTS:
            self.assertFalse(release.target(prefix + 'assets/discovery-map-new.js').exists())
            self.assertFalse(release.target(prefix + 'assets/discovery-map-new.css').exists())
        record = json.loads(next(release.BACKUP_ROOT.glob('*/release.json')).read_text())
        self.assertEqual(record['state'], 'rolled_back')
        for name, details in record['files'].items():
            if details['before'] is not None:
                self.assertEqual(release.digest(next(release.BACKUP_ROOT.glob('*')) / name), details['before'])

    def test_live_drift_refuses_before_backup_or_write(self):
        release.target('backend/resources/js/discovery-map.js').write_text('concurrent change')
        result, writes, caches = self.invoke()
        self.assertEqual((result, len(writes), caches), (1, 0, 0))
        self.assertFalse(release.BACKUP_ROOT.exists())


if __name__ == '__main__':
    unittest.main()
