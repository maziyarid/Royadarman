<?php

namespace Tests\Feature;

use App\Models\OutboxEvent;
use App\Models\PatientCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class NotificationCallbackTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-callback-secret';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('royadarman.sms.callback_secret', self::SECRET);
    }

    public function test_signed_callback_promotes_sent_to_delivered(): void
    {
        $delivery = $this->seedDelivery('provider-1', 'sent');
        $this->postCallback(['reference' => 'provider-1', 'status' => 'delivered'])->assertOk();
        $this->assertDatabaseHas('notification_deliveries', ['id' => $delivery, 'status' => 'delivered', 'failure_code' => null]);
    }

    public function test_status_regression_delivered_to_sent_is_rejected(): void
    {
        $delivery = $this->seedDelivery('provider-2', 'delivered');
        $this->postCallback(['reference' => 'provider-2', 'status' => 'sent'])->assertOk();
        $this->assertDatabaseHas('notification_deliveries', ['id' => $delivery, 'status' => 'delivered']);
    }

    public function test_status_regression_delivered_to_queued_is_rejected(): void
    {
        $delivery = $this->seedDelivery('provider-3', 'delivered');
        $this->postCallback(['reference' => 'provider-3', 'status' => 'queued'])->assertOk();
        $this->assertDatabaseHas('notification_deliveries', ['id' => $delivery, 'status' => 'delivered']);
    }

    public function test_duplicate_callback_is_idempotent(): void
    {
        $delivery = $this->seedDelivery('provider-4', 'sent');
        $this->postCallback(['reference' => 'provider-4', 'status' => 'delivered'])->assertOk();
        $response = $this->postCallback(['reference' => 'provider-4', 'status' => 'delivered'])->assertOk();
        $response->assertJsonPath('data.applied', false);
        $this->assertDatabaseHas('notification_deliveries', ['id' => $delivery, 'status' => 'delivered']);
    }

    public function test_unknown_provider_reference_does_not_create_a_record(): void
    {
        $this->assertSame(0, DB::table('notification_deliveries')->count());
        $this->postCallback(['reference' => 'never-seen', 'status' => 'delivered'])->assertOk();
        $this->assertSame(0, DB::table('notification_deliveries')->count());
    }

    public function test_late_failure_after_delivered_does_not_corrupt_terminal_state(): void
    {
        $delivery = $this->seedDelivery('provider-5', 'delivered');
        $response = $this->postCallback(['reference' => 'provider-5', 'status' => 'failed', 'failure_code' => 'LATE_FAIL'])->assertOk();
        $response->assertJsonPath('data.applied', false);
        $this->assertDatabaseHas('notification_deliveries', ['id' => $delivery, 'status' => 'delivered', 'failure_code' => null]);
    }

    public function test_failed_after_sent_is_accepted_as_terminal(): void
    {
        $delivery = $this->seedDelivery('provider-5b', 'sent');
        $this->postCallback(['reference' => 'provider-5b', 'status' => 'failed', 'failure_code' => 'REJECTED'])->assertOk();
        $this->assertDatabaseHas('notification_deliveries', ['id' => $delivery, 'status' => 'failed', 'failure_code' => 'REJECTED']);
    }

    public function test_stale_timestamp_is_rejected(): void
    {
        $this->seedDelivery('provider-6', 'sent');
        $timestamp = (string) (time() - 600);
        $body = json_encode(['reference' => 'provider-6', 'status' => 'delivered'], JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, self::SECRET);
        $this->postSigned($body, $timestamp, $signature)->assertUnauthorized();
    }

    public function test_missing_secret_rejects_all_callbacks(): void
    {
        config()->set('royadarman.sms.callback_secret', '');
        $this->seedDelivery('provider-7', 'sent');
        $this->postCallback(['reference' => 'provider-7', 'status' => 'delivered'])->assertUnauthorized();
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $this->seedDelivery('provider-8', 'sent');
        $body = json_encode(['reference' => 'provider-8', 'status' => 'delivered'], JSON_THROW_ON_ERROR);
        $timestamp = (string) time();
        $this->postSigned($body, $timestamp, 'totally-wrong-signature')->assertUnauthorized();
    }

    public function test_validation_rejects_unknown_status_value(): void
    {
        $this->seedDelivery('provider-9', 'sent');
        $this->postCallback(['reference' => 'provider-9', 'status' => 'bogus'])->assertStatus(422);
    }

    public function test_callback_route_is_outside_csrf_protection(): void
    {
        $delivery = $this->seedDelivery('provider-10', 'sent');
        $this->withSession(['_token' => 'test-token'])
            ->postCallback(['reference' => 'provider-10', 'status' => 'delivered'])
            ->assertOk();
        $this->assertDatabaseHas('notification_deliveries', ['id' => $delivery, 'status' => 'delivered']);
    }

    private function seedDelivery(string $reference, string $status): string
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $case = PatientCase::query()->create([
            'public_reference' => 'RD-'.strtoupper(Str::random(8)),
            'patient_user_id' => $patient->id,
            'service_type' => 'guidance_referral',
            'status' => 'submitted',
            'patient_mobile' => '09121234567',
            'patient_mobile_hash' => hash('sha256', Str::random()),
            'budget_band' => 'call',
            'version' => 1,
        ]);
        $event = OutboxEvent::query()->create([
            'event_type' => 'case.submitted',
            'aggregate_type' => PatientCase::class,
            'aggregate_id' => $case->id,
            'recipient_locale' => 'fa',
            'payload' => ['template_key' => 'case_submitted'],
            'deduplication_key' => 'cb-'.Str::random(6),
            'available_at' => now(),
        ]);
        $id = (string) Str::ulid();
        DB::table('notification_deliveries')->insert([
            'id' => $id,
            'outbox_event_id' => $event->id,
            'channel' => 'sms',
            'provider_reference' => $reference,
            'status' => $status,
            'recipient_locale' => 'fa',
            'template_key' => 'case_submitted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function postCallback(array $payload)
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, self::SECRET);

        return $this->postSigned($body, $timestamp, $signature);
    }

    private function postSigned(string $body, string $timestamp, string $signature)
    {
        return $this->call('POST', '/api/v1/notifications/callback', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CALLBACK_TIMESTAMP' => $timestamp,
            'HTTP_X_CALLBACK_SIGNATURE' => $signature,
        ], $body);
    }
}
