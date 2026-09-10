<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class ReleaseIdentityTest extends TestCase
{
    use RefreshDatabase;

    private function identity(): array
    {
        $path = storage_path('app/release-identity.json');
        File::delete($path);
        $this->artisan('royadarman:release-identity --write')->assertSuccessful();
        $this->assertTrue(File::exists($path));

        return json_decode((string) File::get($path), true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_release_identity_emits_commit_lock_hash_and_build_time(): void
    {
        $payload = $this->identity();
        $this->assertArrayHasKey('composer_lock_sha256', $payload);
        $this->assertArrayHasKey('built_at', $payload);
        $this->assertArrayHasKey('commit', $payload);
        $this->assertNotNull($payload['composer_lock_sha256']);
    }

    public function test_write_option_persists_release_identity_json(): void
    {
        $payload = $this->identity();
        $this->assertSame(['commit', 'built_at', 'composer_lock_sha256', 'composer_lock_modified_at'], array_keys($payload));
        File::delete(storage_path('app/release-identity.json'));
    }

    public function test_env_override_commit_takes_precedence_over_git_state(): void
    {
        putenv('ROYADARMAN_RELEASE_COMMIT=abc123def456');
        try {
            $payload = $this->identity();
            $this->assertSame('abc123def456', $payload['commit']);
        } finally {
            putenv('ROYADARMAN_RELEASE_COMMIT');
            File::delete(storage_path('app/release-identity.json'));
        }
    }
}
