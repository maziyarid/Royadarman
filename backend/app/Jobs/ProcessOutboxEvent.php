<?php

namespace App\Jobs;

use App\Domain\Operations\Contracts\NotificationSender;
use App\Models\OutboxEvent;
use App\Models\PatientCase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class ProcessOutboxEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public array $backoff = [30, 120, 600, 1800];

    public function __construct(public readonly string $outboxEventId)
    {
        $this->onQueue('notifications');
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('outbox:'.$this->outboxEventId))->expireAfter(120)];
    }

    public function handle(NotificationSender $sender): void
    {
        $event = OutboxEvent::query()->findOrFail($this->outboxEventId);
        if ($event->processed_at) {
            return;
        }

        $delivery = DB::table('notification_deliveries')->where('outbox_event_id', $event->id)->where('channel', 'sms')->first();
        if ($delivery?->status === 'sent') {
            $event->update(['processed_at' => now()]);
            return;
        }

        if (! $delivery) {
            DB::table('notification_deliveries')->insert([
                'id' => (string) Str::ulid(), 'outbox_event_id' => $event->id, 'channel' => 'sms', 'status' => 'sending',
                'recipient_locale' => $event->recipient_locale ?? 'fa', 'template_key' => (string) ($event->payload['template_key'] ?? $event->event_type),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $case = $event->aggregate_type === PatientCase::class ? PatientCase::query()->with('patient')->find($event->aggregate_id) : null;
        if (! $case?->patient?->phone) {
            throw new RuntimeException('Notification recipient is unavailable.');
        }

        $parameters = array_filter($event->payload, fn ($value, $key) => $key !== 'template_key' && is_scalar($value), ARRAY_FILTER_USE_BOTH);
        $reference = $sender->send($case->patient->phone, (string) ($event->payload['template_key'] ?? $event->event_type), $event->recipient_locale ?? $case->patient->locale, $parameters, $event->id);

        DB::transaction(function () use ($event, $reference): void {
            DB::table('notification_deliveries')->where('outbox_event_id', $event->id)->where('channel', 'sms')->update(['status' => 'sent', 'provider_reference' => $reference, 'updated_at' => now()]);
            OutboxEvent::query()->whereKey($event->id)->update(['processed_at' => now(), 'attempts' => DB::raw('attempts + 1')]);
        });
    }

    public function failed(Throwable $exception): void
    {
        DB::table('notification_deliveries')->where('outbox_event_id', $this->outboxEventId)->where('channel', 'sms')->update(['status' => 'failed', 'failure_code' => class_basename($exception), 'updated_at' => now()]);
        OutboxEvent::query()->whereKey($this->outboxEventId)->update(['attempts' => DB::raw('attempts + 1')]);
    }
}
