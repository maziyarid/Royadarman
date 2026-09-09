<?php

namespace Tests\Feature;

use App\Domain\Operations\Contracts\NotificationSender;
use App\Jobs\ProcessOutboxEvent;
use App\Models\ClinicalDocument;
use App\Models\OutboxEvent;
use App\Models\PatientCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CapturingNotificationSender implements NotificationSender
{
    public int $calls = 0;

    public function send(string $mobile, string $template, string $locale, array $parameters, string $idempotencyKey): string
    {
        $this->calls++;
        return 'provider-1';
    }
}

class OperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_outbox_delivery_is_localised_and_idempotent_without_storing_message_body(): void
    {
        $sender = new CapturingNotificationSender;
        $this->app->instance(NotificationSender::class, $sender);
        $patient = User::factory()->create(['role' => 'patient', 'locale' => 'ar', 'phone' => '09121234567', 'phone_hash' => hash('sha256', 'notify')]);
        $case = $this->patientCase($patient);
        $event = OutboxEvent::query()->create(['event_type' => 'case.submitted', 'aggregate_type' => PatientCase::class, 'aggregate_id' => $case->id, 'recipient_locale' => 'ar', 'payload' => ['template_key' => 'case_submitted', 'reference' => $case->public_reference], 'deduplication_key' => 'notify-1', 'available_at' => now()]);

        (new ProcessOutboxEvent($event->id))->handle($sender);
        (new ProcessOutboxEvent($event->id))->handle($sender);

        $this->assertSame(1, $sender->calls);
        $this->assertDatabaseHas('notification_deliveries', ['outbox_event_id' => $event->id, 'recipient_locale' => 'ar', 'status' => 'sent']);
        $this->assertFalse(DB::getSchemaBuilder()->hasColumn('notification_deliveries', 'body'));
    }

    public function test_signed_callback_updates_delivery_and_rejects_invalid_signature(): void
    {
        config()->set('royadarman.sms.callback_secret', 'test-secret');
        $patient = User::factory()->create(['role' => 'patient']);
        $case = $this->patientCase($patient);
        $event = OutboxEvent::query()->create(['event_type' => 'case.submitted', 'aggregate_type' => PatientCase::class, 'aggregate_id' => $case->id, 'recipient_locale' => 'fa', 'payload' => ['template_key' => 'case_submitted'], 'deduplication_key' => 'callback-1', 'available_at' => now()]);
        DB::table('notification_deliveries')->insert(['id' => (string) Str::ulid(), 'outbox_event_id' => $event->id, 'channel' => 'sms', 'provider_reference' => 'provider-9', 'status' => 'sent', 'recipient_locale' => 'fa', 'template_key' => 'case_submitted', 'created_at' => now(), 'updated_at' => now()]);
        $body = json_encode(['reference' => 'provider-9', 'status' => 'delivered'], JSON_THROW_ON_ERROR);
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, 'test-secret');
        $this->call('POST', '/api/v1/notifications/callback', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CALLBACK_TIMESTAMP' => $timestamp, 'HTTP_X_CALLBACK_SIGNATURE' => $signature], $body)->assertOk();
        $this->assertDatabaseHas('notification_deliveries', ['provider_reference' => 'provider-9', 'status' => 'delivered']);
        $this->call('POST', '/api/v1/notifications/callback', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CALLBACK_TIMESTAMP' => $timestamp, 'HTTP_X_CALLBACK_SIGNATURE' => 'wrong'], $body)->assertUnauthorized();
    }

    public function test_due_retention_deletes_private_object_and_marks_record_without_a_default_duration(): void
    {
        $this->assertNull(config('royadarman.retention.document_days'));
        Storage::fake('private-opg');
        $patient = User::factory()->create(['role' => 'patient']);
        $case = $this->patientCase($patient);
        $document = ClinicalDocument::query()->create(['case_id' => $case->id, 'storage_disk' => 'private-opg', 'storage_key' => 'cases/test/opg.upload', 'original_name' => 'opg.png', 'detected_mime' => 'image/png', 'byte_size' => 8, 'sha256' => hash('sha256', 'contents'), 'status' => 'approved']);
        Storage::disk('private-opg')->put($document->storage_key, 'contents');
        DB::table('retention_jobs')->insert(['id' => (string) Str::ulid(), 'resource_type' => ClinicalDocument::class, 'resource_id' => $document->id, 'status' => 'pending', 'execute_after' => now()->subSecond(), 'created_at' => now(), 'updated_at' => now()]);
        $this->artisan('retention:run')->assertSuccessful();
        Storage::disk('private-opg')->assertMissing($document->storage_key);
        $this->assertDatabaseHas('clinical_documents', ['id' => $document->id, 'status' => 'deleted']);
    }

    private function patientCase(User $patient): PatientCase
    {
        return PatientCase::query()->create(['public_reference' => 'RD-'.strtoupper(Str::random(8)), 'patient_user_id' => $patient->id, 'service_type' => 'guidance_referral', 'status' => 'submitted', 'patient_mobile' => '09121234567', 'patient_mobile_hash' => hash('sha256', Str::random()), 'budget_band' => 'call']);
    }
}
