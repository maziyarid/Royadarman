<?php

namespace Tests\Feature;

use App\Domain\Operations\Contracts\NotificationSender;
use App\Jobs\ProcessOutboxEvent;
use App\Models\OutboxEvent;
use App\Models\PatientCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class W08NotificationEligibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmed_delivery_is_not_sent_again_after_a_worker_timeout(): void
    {
        $event = $this->event();
        $this->delivery($event, 'delivered');
        $sender = $this->createMock(NotificationSender::class);
        $sender->expects($this->never())->method('send');
        (new ProcessOutboxEvent($event->id))->handle($sender);
        $this->assertNotNull($event->refresh()->processed_at);
        $this->assertDatabaseHas('notification_deliveries', ['outbox_event_id' => $event->id, 'status' => 'delivered', 'failure_code' => null]);
    }

    public function test_exhausted_job_cannot_downgrade_a_delivered_receipt(): void
    {
        $event = $this->event();
        $this->delivery($event, 'delivered');
        (new ProcessOutboxEvent($event->id))->failed(new \RuntimeException('Synthetic timeout'));
        $this->assertDatabaseHas('notification_deliveries', ['outbox_event_id' => $event->id, 'status' => 'delivered', 'failure_code' => null]);
    }

    public function test_inactive_patient_never_receives_a_late_message(): void
    {
        $event = $this->event();
        PatientCase::query()->findOrFail($event->aggregate_id)->patient->update(['is_active' => false]);
        $sender = $this->createMock(NotificationSender::class);
        $sender->expects($this->never())->method('send');
        try {
            (new ProcessOutboxEvent($event->id))->handle($sender);
        } catch (\RuntimeException $exception) {
            $this->assertSame('Notification recipient is unavailable.', $exception->getMessage());
        }
        $this->assertFalse(DB::table('notification_deliveries')->where('outbox_event_id', $event->id)->whereIn('status', ['sent', 'delivered'])->exists());
    }

    public function test_external_template_receives_only_approved_parameters(): void
    {
        $event = $this->event();
        $event->update(['payload' => ['template_key' => 'case_submitted', 'reference' => 'RD-SYNTHETIC', 'internal_note' => 'SYNTHETIC-PRIVATE', 'recipient_user_id' => 99999]]);
        $sender = $this->createMock(NotificationSender::class);
        $sender->expects($this->once())->method('send')->with('09120000001', 'case_submitted', 'fa', ['reference' => 'RD-SYNTHETIC'], $event->id)->willReturn('synthetic-accepted');
        (new ProcessOutboxEvent($event->id))->handle($sender);
    }

    private function event(): OutboxEvent
    {
        $patient = User::factory()->create(['role' => 'patient', 'is_active' => true, 'locale' => 'fa', 'phone' => '09120000001']);
        $case = PatientCase::query()->create(['public_reference' => 'RD-'.Str::random(8), 'patient_user_id' => $patient->id, 'service_type' => 'guidance_referral', 'status' => 'submitted', 'patient_mobile' => '09120000001', 'patient_mobile_hash' => hash('sha256', Str::random()), 'budget_band' => 'call', 'source_language' => 'fa']);

        return OutboxEvent::query()->create(['event_type' => 'case.submitted', 'aggregate_type' => PatientCase::class, 'aggregate_id' => $case->id, 'recipient_locale' => 'fa', 'payload' => ['template_key' => 'case_submitted'], 'deduplication_key' => 'w08-'.Str::ulid(), 'available_at' => now()]);
    }

    private function delivery(OutboxEvent $event, string $status): void
    {
        DB::table('notification_deliveries')->insert(['id' => (string) Str::ulid(), 'outbox_event_id' => $event->id, 'channel' => 'sms', 'status' => $status, 'provider_reference' => $event->id, 'recipient_locale' => 'fa', 'template_key' => 'case_submitted', 'failure_code' => null, 'created_at' => now(), 'updated_at' => now()]);
    }
}
