<?php

namespace App\Console\Commands;

use App\Domain\Operations\Services\OperationalHeartbeats;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

final class OperationalHeartbeat extends Command
{
    protected $signature = 'operations:heartbeat {--activate : Enable probes after verified deployment} {--retire : Disable probes and retire unreserved owned jobs before rollback}';

    protected $description = 'Persist private scheduler and queue execution evidence without external delivery.';

    public function handle(OperationalHeartbeats $heartbeats): int
    {
        try {
            if ($this->option('activate') && $this->option('retire')) {
                throw new \RuntimeException('Conflicting heartbeat lifecycle options.');
            }
            if ($this->option('activate')) {
                $heartbeats->activate();
            } elseif ($this->option('retire')) {
                $heartbeats->retire();
            } else {
                $heartbeats->tick();
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            Log::warning('Operational heartbeat could not be recorded.', ['exception_class' => $exception::class]);
            $this->error('Operational heartbeat evidence is unavailable.');

            return self::FAILURE;
        }
    }
}
