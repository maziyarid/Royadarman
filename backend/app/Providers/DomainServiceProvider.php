<?php

namespace App\Providers;

use App\Domain\Finance\Contracts\LedgerService as LedgerServiceContract;
use App\Domain\Finance\Contracts\PaymentService as PaymentServiceContract;
use App\Domain\Finance\Services\LedgerService;
use App\Domain\Finance\Services\PaymentService;
use App\Domain\Matching\Contracts\MatchingService as MatchingServiceContract;
use App\Domain\Matching\Services\MatchingService;
use App\Domain\Operations\Contracts\Idempotency as IdempotencyContract;
use App\Domain\Operations\Contracts\NotificationSender as NotificationSenderContract;
use App\Domain\Operations\Contracts\Outbox as OutboxContract;
use App\Domain\Operations\Services\Idempotency;
use App\Domain\Operations\Services\NotificationSender;
use App\Domain\Operations\Services\Outbox;
use App\Domain\Scheduling\Contracts\SlotService as SlotServiceContract;
use App\Domain\Scheduling\Services\SlotService;
use Illuminate\Support\ServiceProvider;

class DomainServiceProvider extends ServiceProvider
{
    public array $bindings = [
        MatchingServiceContract::class => MatchingService::class,
        SlotServiceContract::class => SlotService::class,
        PaymentServiceContract::class => PaymentService::class,
        LedgerServiceContract::class => LedgerService::class,
        OutboxContract::class => Outbox::class,
        IdempotencyContract::class => Idempotency::class,
        NotificationSenderContract::class => NotificationSender::class,
    ];

    public function register(): void
    {
        foreach ($this->bindings as $abstract => $concrete) {
            $this->app->bind($abstract, $concrete);
        }
    }

    public function boot(): void
    {
        //
    }
}
