<?php

namespace App\Domain\Operations\Services;

use App\Domain\Operations\Contracts\SmsProvider;
use App\Domain\Operations\Exceptions\SmsDeliveryException;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

final class SmsManager
{
    /** @var array<string, \Closure(Container): SmsProvider> */
    private array $providerFactories = [];

    public function __construct(
        private readonly Container $container,
    ) {}

    /**
     * Register a provider factory keyed by its short name.
     *
     * @param  \Closure(Container): SmsProvider  $factory
     */
    public function extend(string $name, \Closure $factory): void
    {
        $this->providerFactories[$name] = $factory;
    }

    public function provider(?string $name = null): SmsProvider
    {
        $name ??= (string) config('royadarman.sms.provider', 'http');

        if (! isset($this->providerFactories[$name])) {
            throw new InvalidArgumentException("SMS provider [{$name}] is not registered.");
        }

        return ($this->providerFactories[$name])($this->container);
    }

    public function isConfigured(): bool
    {
        try {
            $this->provider();

            return true;
        } catch (SmsDeliveryException|InvalidArgumentException) {
            return false;
        }
    }
}
