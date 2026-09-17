<?php

namespace Tests\Feature;

use App\Domain\Identity\Services\SessionInventoryService;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class SessionInventoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['session.driver' => 'database', 'session.table' => 'sessions']);
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
        $this->assertSame(1, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->assertSame(1, DB::table('sessions')->where('user_id', $other->id)->count());
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
        $this->assertSame(['keep'], DB::table('sessions')->where('user_id', $user->id)->pluck('id')->all());
    }

    public function test_force_revoke_all_is_audited_for_operator(): void
    {
        $subject = User::factory()->create(['role' => 'patient']);
        $actor = User::factory()->create(['role' => 'tech_admin']);
        $this->insertSession('x', $subject->id, '9.9.9.9', 'Chrome');

        $deleted = app(SessionInventoryService::class)->forceRevokeAll($subject, $actor, 'compromise_response');
        $this->assertSame(1, $deleted);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $subject->id)->count());
        $this->assertTrue(
            AuditEvent::query()->where('action', 'session.force_revoke_all')->where('actor_user_id', $actor->id)->exists()
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

    private function insertSession(string $id, int $userId, string $ip, string $ua): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $userId,
            'ip_address' => $ip,
            'user_agent' => $ua,
            'payload' => base64_encode('test'),
            'last_activity' => time(),
        ]);
    }
}
