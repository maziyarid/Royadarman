<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class ReleaseIdentityTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->path = storage_path('framework/testing/release-identity.json');
        File::ensureDirectoryExists(dirname($this->path));
        File::delete($this->path);
    }

    protected function tearDown(): void
    {
        File::delete($this->path);
        parent::tearDown();
    }

    private function identity(): array
    {
        File::delete($this->path);
        $this->artisan('royadarman:release-identity', [
            '--write' => true,
            '--path' => $this->path,
        ])->assertSuccessful();
        $this->assertTrue(File::exists($this->path));

        return json_decode((string) File::get($this->path), true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_release_identity_emits_commit_lock_hash_and_build_time(): void
    {
        $payload = $this->identity();
        $this->assertArrayHasKey('composer_lock_sha256', $payload);
        $this->assertArrayHasKey('built_at', $payload);
        $this->assertArrayHasKey('commit', $payload);
        $commit = $payload['commit'];
        $this->assertTrue(
            $commit === null || (is_string($commit) && (bool) preg_match('/^[0-9a-f]{40}$/', $commit)),
            'commit must be null or a 40-character SHA',
        );
        $this->assertTrue($payload['composer_lock_sha256'] === null || is_string($payload['composer_lock_sha256']));
    }

    public function test_write_option_persists_release_identity_json(): void
    {
        $payload = $this->identity();
        $this->assertSame(['commit', 'built_at', 'composer_lock_sha256', 'composer_lock_modified_at'], array_keys($payload));
        File::delete($this->path);
    }

    public function test_env_override_commit_takes_precedence_over_git_state(): void
    {
        putenv('ROYADARMAN_RELEASE_COMMIT=abc123def456');
        try {
            $payload = $this->identity();
            $this->assertSame('abc123def456', $payload['commit']);
        } finally {
            putenv('ROYADARMAN_RELEASE_COMMIT');
            File::delete($this->path);
        }
    }

    public function test_write_never_touches_the_production_release_identity_file(): void
    {
        $production = storage_path('app/release-identity.json');
        $existed = File::exists($production);
        $before = $existed ? File::get($production) : null;
        $mtime = $existed ? File::lastModified($production) : null;

        $this->identity();

        if ($existed) {
            $this->assertSame($before, File::get($production));
            $this->assertSame($mtime, File::lastModified($production));
        } else {
            $this->assertFalse(File::exists($production));
        }
    }
}
