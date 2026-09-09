<?php

namespace App\Console\Commands;

use App\Jobs\ProcessOutboxEvent;
use App\Models\OutboxEvent;
use Illuminate\Console\Command;

final class DispatchOutbox extends Command
{
    protected $signature = 'outbox:dispatch {--limit=100}';

    protected $description = 'Dispatch durable, unprocessed outbox events.';

    public function handle(): int
    {
        OutboxEvent::query()->whereNull('processed_at')->where('available_at', '<=', now())->orderBy('created_at')->limit((int) $this->option('limit'))->pluck('id')->each(fn (string $id) => ProcessOutboxEvent::dispatch($id)->afterCommit());

        return self::SUCCESS;
    }
}
