<?php

namespace Tests\Feature;

use App\Models\OutboxEvent;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class NotificationProviderReferenceUniquenessTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_reference_cannot_identify_more_than_one_delivery(): void
    {
        $firstEvent = $this->outboxEvent('provider-ref-1');
        $secondEvent = $this->outboxEvent('provider-ref-2');

        $this->insertDelivery($firstEvent->id, 'provider-collision');

        $this->expectException(QueryException::class);
        $this->insertDelivery($secondEvent->id, 'provider-collision');
    }

    private function outboxEvent(string $deduplicationKey): OutboxEvent
    {
        return OutboxEvent::query()->create([
            'event_type' => 'case.submitted',
            'aggregate_type' => 'test',
            'aggregate_id' => (string) Str::ulid(),
            'recipient_locale' => 'fa',
            'payload' => ['template_key' => 'case_submitted'],
            'deduplication_key' => $deduplicationKey,
            'available_at' => now(),
        ]);
    }

    private function insertDelivery(string $eventId, string $providerReference): void
    {
        DB::table('notification_deliveries')->insert([
            'id' => (string) Str::ulid(),
            'outbox_event_id' => $eventId,
            'channel' => 'sms',
            'provider_reference' => $providerReference,
            'status' => 'sent',
            'recipient_locale' => 'fa',
            'template_key' => 'case_submitted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
