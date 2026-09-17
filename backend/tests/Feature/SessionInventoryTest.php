<?php

namespace Tests\Feature;

use App\Domain\Identity\Services\SessionInventoryService;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

final class SessionInventoryTest extends TestCase
{
    use RefreshDatabase;

    private static bool $failAuditWrites = false;

    protected function setUp(): void
    {
        parent::setUp();
        self::$failAuditWrites = false;
        config(['session.driver' => 'database', 'session.table' => 'sessions', 'session.connection' => null]);
    }

    public function test_service_lists_and_revokes_sessions_for_owner_only(): void
    {
        $user = User::factory()->create(['role' => 'patient']);
        $other = User::factory()->create(['role' => 'patient']);

        $this->insertSession('sess-a', $user->id, '10.0.0.1', 'Mozilla/5.0 Chrome/120 Windows');
        $this->insertSession('sess-b', $user->id, '10.0.0.2', 'Mozilla/5.0 Firefox/121 Linux');
        $this->insertSession('sess-other', $other->id, '10.0.0.3', 'Mozilla/5.0 Safari/17 Mac OS X');

        $service = app(SessionInventoryService::class);
        $list = $service->listForUser($user, 'sess-a');

        $this->assertCount(2, $list);
        $this->assertTrue($list->firstWhere('id', 'sess-a')['is_current']);
        $this->assertFalse($list->firstWhere('id', 'sess-b')['is_current']);
        $this->assertStringContainsString('Chrome', $list->firstWhere('id', 'sess-a')['device_label']);

        $this->assertTrue($service->revokeOne($user, 'sess-b'));
        $this->assertFalse($service->revokeOne($user, 'sess-other'));
        $this->assertSame(1, $this->sessionStore()->where('user_id', $user->id)->count());
        $this->assertSame(1, $this->sessionStore()->where('user_id', $other->id)->count());
        $this->assertSame(1, AuditEvent::query()->where('action', 'session.revoke_one')->count());
    }

    public function test_revoke_others_keeps_current_session(): void
    {
        $user = User::factory()->create(['role' => 'coordinator']);
        $this->insertSession('keep', $user->id, '1.1.1.1', 'Chrome');
        $this->insertSession('drop-1', $user->id, '2.2.2.2', 'Firefox');
        $this->insertSession('drop-2', $user->id, '3.3.3.3', 'Safari');

        $deleted = app(SessionInventoryService::class)->revokeOthers($user, 'keep');
        $this->assertSame(2, $deleted);
        $this->assertSame(['keep'], $this->sessionStore()->where('user_id', $user->id)->pluck('id')->all());
    }

    public function test_force_revoke_all_is_audited_for_operator(): void
    {
        $subject = User::factory()->create(['role' => 'patient']);
        $actor = User::factory()->create(['role' => 'tech_admin']);
        $this->insertSession('x', $subject->id, '9.9.9.9', 'Chrome');

        $deleted = app(SessionInventoryService::class)->forceRevokeAll($subject, $actor, 'compromise_response');
        $this->assertSame(1, $deleted);
        $this->assertSame(0, $this->sessionStore()->where('user_id', $subject->id)->count());
        $this->assertTrue(
            AuditEvent::query()->where('action', 'session.force_revoke_all')->where('actor_user_id', $actor->id)->exists()
        );
        $this->assertTrue(
            AuditEvent::query()->where('action', 'session.revoke_all')->where('actor_user_id', $actor->id)->exists()
        );
    }

    public function test_same_connection_audit_failure_rolls_back_session_delete(): void
    {
        $user = User::factory()->create(['role' => 'patient']);
        $this->insertSession('keep-me', $user->id, '10.0.0.1', 'Chrome');

        $this->causeAuditWritesToFail();

        try {
            app(SessionInventoryService::class)->revokeOne($user, 'keep-me');
            $this->fail('expected audit failure to abort the same-connection transaction');
        } catch (RuntimeException $e) {
            $this->assertSame('simulated audit write failure', $e->getMessage());
        }

        $this->assertSame(1, $this->sessionStore()->where('id', 'keep-me')->count());
        $this->assertSame(0, AuditEvent::query()->where('action', 'session.revoke_one')->count());
    }

    public function test_split_session_connection_inventory_ignores_default_connection_rows(): void
    {
        $user = User::factory()->create(['role' => 'patient']);
        $this->useSplitSessionStore();

        DB::connection()->table('sessions')->insert([
            'id' => 'decoy-default',
            'user_id' => $user->id,
            'ip_address' => '9.9.9.9',
            'user_agent' => 'Mozilla/5.0 decoy',
            'payload' => base64_encode('decoy'),
            'last_activity' => time(),
        ]);
        $this->insertSession('real-sess', $user->id, '1.1.1.1', 'Mozilla/5.0 Chrome/120 Windows');

        $service = app(SessionInventoryService::class);
        $list = $service->listForUser($user, 'real-sess');

        $this->assertCount(1, $list);
        $this->assertSame('real-sess', $list->first()['id']);
        $this->assertTrue($list->first()['is_current']);

        $this->assertTrue($service->revokeOne($user, 'real-sess'));
        $this->assertSame(0, $this->sessionStore()->where('id', 'real-sess')->count());
        $this->assertSame(1, DB::connection()->table('sessions')->where('id', 'decoy-default')->count());
        $this->assertSame(1, AuditEvent::query()->where('action', 'session.revoke_one')->count());
    }

    public function test_split_session_connection_audit_failure_does_not_undo_revoke(): void
    {
        $user = User::factory()->create(['role' => 'patient']);
        $this->useSplitSessionStore();
        $this->insertSession('gone', $user->id, '1.1.1.1', 'Chrome');

        $this->causeAuditWritesToFail();

        $ok = app(SessionInventoryService::class)->revokeOne($user, 'gone');
        $this->assertTrue($ok);

        $this->assertSame(0, $this->sessionStore()->where('id', 'gone')->count());
        $this->assertSame(0, AuditEvent::query()->where('action', 'session.revoke_one')->count());
    }

    public function test_security_change_reasons_are_audited_on_revoke_all(): void
    {
        $user = User::factory()->create(['role' => 'coordinator']);
        $this->insertSession('old-device', $user->id, '1.1.1.1', 'Chrome');

        $deleted = app(SessionInventoryService::class)->revokeAll($user, null, 'staff_role_change');
        $this->assertSame(1, $deleted);
        $this->assertSame(0, $this->sessionStore()->where('user_id', $user->id)->count());
        $this->assertTrue(
            AuditEvent::query()
                ->where('action', 'session.revoke_all')
                ->get()
                ->contains(fn (AuditEvent $event): bool => $event->reason === 'staff_role_change' && $event->resource_id === (string) $user->id)
        );

        $this->insertSession('pre-mfa', $user->id, '2.2.2.2', 'Firefox');
        $deletedMfa = app(SessionInventoryService::class)->revokeAll($user, null, 'staff_mfa_replaced');
        $this->assertSame(1, $deletedMfa);
        $this->assertTrue(
            AuditEvent::query()
                ->where('action', 'session.revoke_all')
                ->get()
                ->contains(fn (AuditEvent $event): bool => $event->reason === 'staff_mfa_replaced')
        );
    }

    public function test_profile_page_shows_sessions_section_for_authenticated_user(): void
    {
        $user = User::factory()->create(['role' => 'patient', 'locale' => 'en']);
        $this->actingAs($user)
            ->get('/en/panel/profile')
            ->assertOk()
            ->assertSee(__('panel.sessions.title'), false)
            ->assertSee(__('panel.profile.preferences'), false);
    }

    public function test_api_lists_sessions_when_using_database_driver(): void
    {
        $user = User::factory()->create(['role' => 'patient']);
        $this->insertSession('api-sess', $user->id, '8.8.8.8', 'Mozilla/5.0 Chrome/120 Windows');

        $this->actingAs($user)
            ->getJson('/api/v1/me/sessions')
            ->assertOk()
            ->assertJsonPath('data.sessions.0.id', 'api-sess');
    }

    private function causeAuditWritesToFail(): void
    {
        self::$failAuditWrites = true;
        AuditEvent::creating(static function (): void {
            if (self::$failAuditWrites) {
                throw new RuntimeException('simulated audit write failure');
            }
        });
    }

    private function useSplitSessionStore(): void
    {
        config([
            'database.connections.session_store' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
            'session.driver' => 'database',
            'session.connection' => 'session_store',
            'session.table' => 'sessions',
        ]);

        DB::purge('session_store');
        DB::reconnect('session_store');

        Schema::connection('session_store')->create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('payload');
            $table->integer('last_activity')->index();
        });
    }

    private function sessionStore()
    {
        $connection = config('session.connection');

        return DB::connection(is_string($connection) && $connection !== '' ? $connection : null)
            ->table((string) config('session.table', 'sessions'));
    }

    private function insertSession(string $id, int $userId, string $ip, string $ua): void
    {
        $this->sessionStore()->insert([
            'id' => $id,
            'user_id' => $userId,
            'ip_address' => $ip,
            'user_agent' => $ua,
            'payload' => base64_encode('test'),
            'last_activity' => time(),
        ]);
    }
}
