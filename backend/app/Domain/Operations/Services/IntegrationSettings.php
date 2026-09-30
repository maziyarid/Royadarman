<?php

namespace App\Domain\Operations\Services;

use App\Models\IntegrationSetting;
use Illuminate\Support\Collection;
use Throwable;

final class IntegrationSettings
{
    /**
     * The registry is deliberately closed: the admin UI may only write known
     * application integration settings. Secrets are encrypted by the model.
     *
     * @return array<string, array{config:string,secret:bool,type:string,group:string}>
     */
    public static function definitions(): array
    {
        return [
            'neshan_map_api_key' => [
                'config' => 'royadarman.neshan.map_api_key',
                'secret' => true,
                'type' => 'text',
                'group' => 'neshan',
            ],
            'neshan_service_api_key' => [
                'config' => 'royadarman.neshan.service_api_key',
                'secret' => true,
                'type' => 'text',
                'group' => 'neshan',
            ],
            'sms_provider' => [
                'config' => 'royadarman.sms.provider',
                'secret' => false,
                'type' => 'provider',
                'group' => 'sms',
            ],
            'sms_callbacks_enabled' => [
                'config' => 'royadarman.sms.callbacks_enabled',
                'secret' => false,
                'type' => 'boolean',
                'group' => 'sms',
            ],
            'sms_callback_secret' => [
                'config' => 'royadarman.sms.callback_secret',
                'secret' => true,
                'type' => 'text',
                'group' => 'sms',
            ],
            'sms_endpoint' => [
                'config' => 'royadarman.sms.endpoint',
                'secret' => false,
                'type' => 'url',
                'group' => 'sms',
            ],
            'sms_token' => [
                'config' => 'royadarman.sms.token',
                'secret' => true,
                'type' => 'text',
                'group' => 'sms',
            ],
            'tsms_endpoint' => [
                'config' => 'royadarman.sms.tsms.endpoint',
                'secret' => false,
                'type' => 'url',
                'group' => 'tsms',
            ],
            'tsms_username' => [
                'config' => 'royadarman.sms.tsms.username',
                'secret' => true,
                'type' => 'text',
                'group' => 'tsms',
            ],
            'tsms_password' => [
                'config' => 'royadarman.sms.tsms.password',
                'secret' => true,
                'type' => 'text',
                'group' => 'tsms',
            ],
            'tsms_from' => [
                'config' => 'royadarman.sms.tsms.from',
                'secret' => false,
                'type' => 'text',
                'group' => 'tsms',
            ],
            'resend_api_key' => [
                'config' => 'services.resend.key',
                'secret' => true,
                'type' => 'text',
                'group' => 'email',
            ],
            'retention_document_days' => [
                'config' => 'royadarman.retention.document_days',
                'secret' => false,
                'type' => 'integer',
                'group' => 'operations',
            ],
            'referral_grant_ttl_minutes' => [
                'config' => 'royadarman.referral.grant_ttl_minutes',
                'secret' => false,
                'type' => 'integer',
                'group' => 'operations',
            ],
            'scanner_enabled' => [
                'config' => 'royadarman.opg.scanner.enabled',
                'secret' => false,
                'type' => 'boolean',
                'group' => 'operations',
            ],
            'intake_enabled' => [
                'config' => 'royadarman.intake_enabled',
                'secret' => false,
                'type' => 'boolean',
                'group' => 'launch',
            ],
        ];
    }

    public function value(string $key, mixed $fallback = null): mixed
    {
        $definition = self::definitions()[$key] ?? null;
        if ($definition === null) {
            return $fallback;
        }

        try {
            $row = IntegrationSetting::query()->where('key', $key)->first();
            if (! $row) {
                return $fallback;
            }

            $value = $row->value;
            if ($definition['type'] === 'boolean') {
                return filter_var($value, FILTER_VALIDATE_BOOL);
            }
            if ($definition['type'] === 'integer') {
                return is_numeric($value) ? (int) $value : $fallback;
            }

            return $value;
        } catch (Throwable) {
            return $fallback;
        }
    }

    public function applyToRuntimeConfig(): void
    {
        try {
            $rows = IntegrationSetting::query()
                ->whereIn('key', array_keys(self::definitions()))
                ->get();
        } catch (Throwable) {
            // Migrations, first boot, or a broken DB must not make the whole
            // application unavailable. Environment configuration remains the
            // fail-safe fallback.
            return;
        }

        foreach ($rows as $row) {
            $definition = self::definitions()[$row->key] ?? null;
            if ($definition === null) {
                continue;
            }

            try {
                $value = $row->value;
            } catch (Throwable) {
                // Invalid ciphertext is ignored rather than exposed or logged.
                continue;
            }

            if ($definition['type'] === 'boolean') {
                $value = filter_var($value, FILTER_VALIDATE_BOOL);
            } elseif ($definition['type'] === 'integer') {
                if (! is_numeric($value)) {
                    continue;
                }
                $value = (int) $value;
            }

            config()->set($definition['config'], $value);
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function forDisplay(): array
    {
        try {
            /** @var Collection<string, IntegrationSetting> $overrides */
            $overrides = IntegrationSetting::query()
                ->whereIn('key', array_keys(self::definitions()))
                ->get()
                ->keyBy('key');
        } catch (Throwable) {
            $overrides = collect();
        }

        $result = [];

        foreach (self::definitions() as $key => $definition) {
            $row = $overrides->get($key);
            $source = $row ? 'database' : 'environment';
            $configured = false;
            $displayValue = '';

            if ($row) {
                try {
                    $value = $row->value;
                    $configured = $this->hasValue($value, $definition['type']);
                    if (! $definition['secret']) {
                        $displayValue = $definition['type'] === 'boolean'
                            ? (filter_var($value, FILTER_VALIDATE_BOOL) ? '1' : '0')
                            : (string) $value;
                    }
                } catch (Throwable) {
                    $configured = false;
                }
            } else {
                $value = config($definition['config']);
                $configured = $this->hasValue($value, $definition['type']);
                if (! $definition['secret'] && $value !== null) {
                    $displayValue = $definition['type'] === 'boolean'
                        ? ((bool) $value ? '1' : '0')
                        : (string) $value;
                }
            }

            $result[$key] = [
                ...$definition,
                'configured' => $configured,
                'source' => $configured ? $source : 'none',
                'value' => $definition['secret'] ? '' : $displayValue,
                'updated_at' => $row?->updated_at,
            ];
        }

        return $result;
    }

    private function hasValue(mixed $value, string $type): bool
    {
        if ($type === 'boolean') {
            return $value !== null && $value !== '';
        }
        if ($type === 'integer') {
            return is_numeric($value);
        }

        return trim((string) ($value ?? '')) !== '';
    }
}
