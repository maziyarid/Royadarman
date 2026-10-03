<?php

namespace Tests\Feature;

use App\Domain\Operations\Contracts\NotificationSender;
use App\Jobs\ProcessOutboxEvent;
use App\Models\Clinic;
use App\Models\OutboxEvent;
use App\Models\PatientCase;
use App\Models\PolicyVersion;
use App\Models\ReferralProposal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

final class ReferralNotificationCorrelationTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('invalidAggregates')]
    public function test_unrecognised_or_ineligible_referrals_never_use_payload_recipient_overrides(string $variant): void
    {
        $event = $this->acceptedEvent();
        if ($variant === 'unknown') {
            $event->update(['aggregate_type' => User::class]);
        } elseif ($variant === 'missing') {
            $event->update(['aggregate_id' => (string) Str::ulid()]);
        } elseif ($variant === 'wrong_event') {
            $event->update(['event_type' => 'case.submitted']);
        } else {
            ReferralProposal::query()->findOrFail($event->aggregate_id)->update($variant === 'withdrawn' ? ['withdrawn_at' => now()] : ['status' => 'proposed']);
        }
        $event->update(['payload' => ['template_key' => 'referral_accepted', 'mobile' => '09129999999', 'case_id' => (string) Str::ulid()]]);
        $sender = $this->createMock(NotificationSender::class);
        $sender->expects($this->never())->method('send');
        try {
            (new ProcessOutboxEvent($event->id))->handle($sender);
            $this->fail('Invalid persisted aggregate must not produce a recipient.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Notification recipient is unavailable.', $exception->getMessage());
        }
        $this->assertNull($event->refresh()->processed_at);
        $this->assertDatabaseCount('notification_deliveries', 1);
    }

    public static function invalidAggregates(): array
    {
        return array_map(fn ($variant) => [$variant], ['unknown', 'missing', 'wrong_event', 'withdrawn', 'proposed']);
    }

    public function test_provider_retry_reuses_delivery_and_idempotency_key(): void
    {
        $event = $this->acceptedEvent();
        $sender = new class implements NotificationSender
        {
            public array $keys = [];

            public function send(string $mobile, string $template, string $locale, array $parameters, string $idempotencyKey): string
            {
                $this->keys[] = $idempotencyKey;
                if (count($this->keys) === 1) {
                    throw new RuntimeException('Synthetic provider timeout');
                }

                return 'synthetic-retry';
            }
        };
        try {
            (new ProcessOutboxEvent($event->id))->handle($sender);
            $this->fail('First synthetic provider call must fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Synthetic provider timeout', $exception->getMessage());
        }
        $this->assertNull($event->refresh()->processed_at);
        (new ProcessOutboxEvent($event->id))->handle($sender);
        (new ProcessOutboxEvent($event->id))->handle($sender);
        $this->assertSame([$event->id, $event->id], $sender->keys);
        $this->assertDatabaseCount('notification_deliveries', 1);
        $this->assertDatabaseHas('notification_deliveries', ['outbox_event_id' => $event->id, 'status' => 'sent', 'provider_reference' => 'synthetic-retry']);
    }

    private function acceptedEvent(): OutboxEvent
    {
        $patient = User::factory()->create(['role' => 'patient', 'phone' => '09120000000']);
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $case = PatientCase::query()->create(['public_reference' => 'RD-'.strtoupper(Str::random(8)), 'patient_user_id' => $patient->id, 'current_coordinator_id' => $coordinator->id, 'service_type' => 'guidance_referral', 'status' => 'in_coordination', 'patient_mobile' => '09120000000', 'patient_mobile_hash' => hash('sha256', Str::random()), 'budget_band' => 'call', 'source_language' => 'fa']);
        $clinic = Clinic::query()->create(['name' => 'Synthetic retry clinic', 'city' => 'Tehran', 'is_active' => true]);
        $proposal = ReferralProposal::query()->create(['case_id' => $case->id, 'clinic_id' => $clinic->id, 'proposed_by_user_id' => $coordinator->id, 'status' => 'accepted', 'reasoning' => 'synthetic', 'source_language' => 'fa', 'proposed_at' => now(), 'decided_at' => now()]);

        return OutboxEvent::query()->create(['aggregate_type' => ReferralProposal::class, 'aggregate_id' => $proposal->id, 'event_type' => 'referral.accepted', 'payload' => ['template_key' => 'referral_accepted'], 'recipient_locale' => 'fa', 'deduplication_key' => 'referral.accepted.'.$proposal->id, 'available_at' => now()]);
    }

    public function test_committed_referral_acceptance_correlates_calendar_and_one_patient_delivery(): void
    {
        Queue::fake();
        $this->travelTo(now()->setDate(2026, 3, 21)->startOfDay());
        config(['royadarman.intake_enabled' => true, 'royadarman.referral.grant_ttl_minutes' => 1440]);
        $patient = User::factory()->create(['role' => 'patient', 'locale' => 'fa', 'phone' => '09120000000']);
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $case = PatientCase::query()->create(['public_reference' => 'RD-'.strtoupper(Str::random(8)), 'patient_user_id' => $patient->id, 'current_coordinator_id' => $coordinator->id, 'service_type' => 'guidance_referral', 'status' => 'in_coordination', 'patient_mobile' => '09120000000', 'patient_mobile_hash' => hash('sha256', Str::random()), 'budget_band' => 'call', 'source_language' => 'fa']);
        $clinicId = (string) Str::ulid();
        DB::table('clinics')->insert(['id' => $clinicId, 'name' => 'Synthetic clinic', 'city' => 'Tehran', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $proposal = ReferralProposal::query()->create(['case_id' => $case->id, 'clinic_id' => $clinicId, 'proposed_by_user_id' => $coordinator->id, 'status' => 'proposed', 'reasoning' => 'PRIVATE-REFERRAL', 'source_language' => 'fa', 'proposed_at' => now()]);
        $policy = PolicyVersion::query()->create(['policy_key' => 'referral_sharing', 'version' => 'correlation-v1', 'locale' => 'fa', 'content' => 'synthetic consent', 'content_hash' => hash('sha256', 'synthetic consent'), 'published_at' => now()]);
        $url = '/api/v1/cases/'.$case->id.'/referrals/'.$proposal->id.'/decision';
        $payload = ['decision' => 'accepted', 'policy_version' => $policy->version, 'content_hash' => $policy->content_hash];
        $this->actingAs($patient)->postJson($url, $payload)->assertOk();
        $this->postJson($url, $payload)->assertUnprocessable();
        $event = OutboxEvent::query()->where('deduplication_key', 'referral.accepted.'.$proposal->id)->sole();
        $this->assertSame(ReferralProposal::class, $event->aggregate_type);
        $this->assertDatabaseCount('outbox_events', 1);
        $grant = $proposal->grant()->sole();
        $calendar = $this->actingAs($coordinator)->get('/fa/panel/calendar?jmonth=1405-01')->assertOk();
        $entry = $calendar->viewData('events')->where('kind', 'referral_expiry')->sole();
        $this->assertTrue($entry['at']->equalTo($grant->expires_at));
        $this->assertStringContainsString($case->id, $entry['url']);

        $sender = new class implements NotificationSender
        {
            public array $calls = [];

            public function send(string $mobile, string $template, string $locale, array $parameters, string $idempotencyKey): string
            {
                $this->calls[] = compact('mobile', 'template', 'locale', 'parameters', 'idempotencyKey');

                return 'synthetic-referral-1';
            }
        };
        (new ProcessOutboxEvent($event->id))->handle($sender);
        (new ProcessOutboxEvent($event->id))->handle($sender);
        $this->assertCount(1, $sender->calls);
        $this->assertSame('09120000000', $sender->calls[0]['mobile']);
        $this->assertSame('referral_accepted', $sender->calls[0]['template']);
        $this->assertSame($event->id, $sender->calls[0]['idempotencyKey']);
        $this->assertSame([], $sender->calls[0]['parameters']);
        $this->assertDatabaseHas('notification_deliveries', ['outbox_event_id' => $event->id, 'template_key' => 'referral_accepted', 'status' => 'sent']);
        $this->assertNotNull($event->refresh()->processed_at);
        $this->travelBack();
    }
}
