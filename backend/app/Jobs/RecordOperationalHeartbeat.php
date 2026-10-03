<?php

namespace App\Jobs;

use App\Domain\Operations\Services\OperationalHeartbeats;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

final class RecordOperationalHeartbeat implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $backoff = 1;

    public int $timeout = 10;

    public function __construct(public readonly string $component, public readonly int $issuedAt, public readonly string $probeId)
    {
        $this->onConnection('database')->onQueue($component);
    }

    public function handle(OperationalHeartbeats $heartbeats): void
    {
        $heartbeats->recordQueue($this->component, $this->issuedAt, $this->probeId);
    }
}
