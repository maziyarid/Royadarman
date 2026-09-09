<?php

namespace App\Domain\Operations\Services;

use App\Jobs\ProcessOutboxEvent;
use App\Models\OutboxEvent;

final class Outbox
{
    /** @param array<string, scalar|null> $payload */
    public function record(string $type, string $aggregateType, string $aggregateId, array $payload, string $deduplicationKey, ?string $locale = null): OutboxEvent
    {
        $event = OutboxEvent::query()->firstOrCreate(['deduplication_key' => $deduplicationKey], [
            'event_type' => $type,
            'aggregate_type' => $aggregateType,
            'aggregate_id' => $aggregateId,
            'recipient_locale' => $locale,
            'payload' => $payload,
            'available_at' => now(),
        ]);
        if (! $event->processed_at) {
            ProcessOutboxEvent::dispatch($event->id)->afterCommit();
        }
        return $event;
    }
}
