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

    public function test_provider_reference_cannot_identify_more_than_one_delivery_in_the_same_channel(): void
    {
        $firstEvent = $this->outboxEvent('provider-ref-1');
        $secondEvent = $this->outboxEvent('provider-ref-2');

        $this->insertDelivery($firstEvent->id, 'provider-collision', 'sms');

        $this->expectException(QueryException::class);
        $this->insertDelivery($secondEvent->id, 'provider-collision', 'sms');
    }

    public function test_different_channels_may_reuse_the_same_provider_reference(): void
    {
        $firstEvent = $this->outboxEvent('provider-ref-channel-1');
        $secondEvent = $this->outboxEvent('provider-ref-channel-2');

        $this->insertDelivery($firstEvent->id, 'shared-provider-reference', 'sms');
        $this->insertDelivery($secondEvent->id, 'shared-provider-reference', 'email');

        $this->assertSame(2, DB::table('notification_deliveries')
            ->where('provider_reference', 'shared-provider-reference')
            ->count());
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

    private function insertDelivery(string $eventId, string $providerReference, string $channel): void
    {
        DB::table('notification_deliveries')->insert([
            'id' => (string) Str::ulid(),
            'outbox_event_id' => $eventId,
            'channel' => $channel,
            'provider_reference' => $providerReference,
            'status' => 'sent',
            'recipient_locale' => 'fa',
            'template_key' => 'case_submitted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
