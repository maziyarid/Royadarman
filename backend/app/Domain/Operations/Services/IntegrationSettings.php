<?php

namespace App\Domain\Operations\Services;

use App\Models\IntegrationSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

final class IntegrationSettings
{
    /**
     * Keys whose stored override cannot be read. Environment fallback would
     * silently re-enable a value an operator may have deliberately switched off,
     * so the safe value is applied instead. Everything else keeps the
     * environment fallback for availability and is surfaced through diagnostics().
     *
     * @var array<string, bool>
     */
    private const FAIL_CLOSED = ['intake_enabled' => false];

    /** @var array<string, string> */
    private array $problems = [];

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
                $parsed = $this->booleanFromStored($value);
                if ($parsed === null) {
                    $this->note($key, 'invalid');

                    return self::FAIL_CLOSED[$key] ?? $fallback;
                }

                return $parsed;
            }
            if ($definition['type'] === 'integer') {
                if (! is_numeric($value)) {
                    $this->note($key, 'invalid');

                    return $fallback;
                }

                return (int) $value;
            }

            return $value;
        } catch (Throwable $exception) {
            $this->note($key, 'unreadable', $exception);

            return self::FAIL_CLOSED[$key] ?? $fallback;
        }
    }

    public function applyToRuntimeConfig(): void
    {
        try {
            $rows = IntegrationSetting::query()
                ->whereIn('key', array_keys(self::definitions()))
                ->get();
        } catch (Throwable $exception) {
            // Migrations, first boot, or a broken DB must not make the whole
            // application unavailable. Environment configuration remains the
            // fallback, except intake: an unreadable store cannot prove that an
            // operator has not paused new patient acquisition.
            $this->note('*database*', 'database_unavailable', $exception);
            foreach (self::FAIL_CLOSED as $key => $safeValue) {
                config()->set(self::definitions()[$key]['config'], $safeValue);
            }

            return;
        }

        foreach ($rows as $row) {
            $definition = self::definitions()[$row->key] ?? null;
            if ($definition === null) {
                continue;
            }

            try {
                $value = $row->value;
            } catch (Throwable $exception) {
                // Invalid ciphertext: never expose or log the value or message.
                $this->note($row->key, 'unreadable', $exception);
                if (array_key_exists($row->key, self::FAIL_CLOSED)) {
                    config()->set($definition['config'], self::FAIL_CLOSED[$row->key]);
                }

                continue;
            }

            if ($definition['type'] === 'boolean') {
                $parsed = $this->booleanFromStored($value);
                if ($parsed === null) {
                    $this->note($row->key, 'invalid');
                    if (array_key_exists($row->key, self::FAIL_CLOSED)) {
                        config()->set($definition['config'], self::FAIL_CLOSED[$row->key]);
                    }

                    continue;
                }
                $value = $parsed;
            } elseif ($definition['type'] === 'integer') {
                if (! is_numeric($value)) {
                    $this->note($row->key, 'invalid');

                    continue;
                }
                $value = (int) $value;
            }

            config()->set($definition['config'], $value);
        }
    }

    /**
     * Redacted health of stored overrides. Only key names and status codes are
     * returned, never values. "unset" keys are not problems: an absent row is a
     * normal environment-only configuration.
     *
     * @return array{database:string,problems:array<string,string>,problem_count:int}
     */
    public function diagnostics(): array
    {
        try {
            $rows = IntegrationSetting::query()
                ->whereIn('key', array_keys(self::definitions()))
                ->get();
        } catch (Throwable $exception) {
            $this->note('*database*', 'database_unavailable', $exception);

            return ['database' => 'unavailable', 'problems' => [], 'problem_count' => 1];
        }

        $problems = [];
        foreach ($rows as $row) {
            $definition = self::definitions()[$row->key] ?? null;
            if ($definition === null) {
                continue;
            }

            try {
                $value = $row->value;
            } catch (Throwable $exception) {
                $this->note($row->key, 'unreadable', $exception);
                $problems[$row->key] = 'unreadable';

                continue;
            }

            if ($definition['type'] === 'integer' && ! is_numeric($value)) {
                $this->note($row->key, 'invalid');
                $problems[$row->key] = 'invalid';
            } elseif ($definition['type'] === 'boolean' && $this->booleanFromStored($value) === null) {
                $this->note($row->key, 'invalid');
                $problems[$row->key] = 'invalid';
            }
        }

        return ['database' => 'ok', 'problems' => $problems, 'problem_count' => count($problems)];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function forDisplay(): array
    {
        $databaseUnavailable = false;
        try {
            /** @var Collection<string, IntegrationSetting> $overrides */
            $overrides = IntegrationSetting::query()
                ->whereIn('key', array_keys(self::definitions()))
                ->get()
                ->keyBy('key');
        } catch (Throwable $exception) {
            $this->note('*database*', 'database_unavailable', $exception);
            $databaseUnavailable = true;
            $overrides = collect();
        }

        $result = [];

        foreach (self::definitions() as $key => $definition) {
            $row = $overrides->get($key);
            $source = $row ? 'database' : 'environment';
            $configured = false;
            $displayValue = '';
            $problem = $databaseUnavailable ? 'database_unavailable' : null;

            if ($row) {
                try {
                    $value = $row->value;
                    $configured = $this->hasValue($value, $definition['type']);
                    $invalidBoolean = $definition['type'] === 'boolean' && $this->booleanFromStored($value) === null;
                    if ($definition['type'] === 'integer' && ! is_numeric($value)) {
                        $this->note($key, 'invalid');
                        $problem = 'invalid';
                    }
                    if ($invalidBoolean) {
                        $this->note($key, 'invalid');
                        $problem = 'invalid';
                    }
                    if (! $definition['secret']) {
                        if ($invalidBoolean) {
                            $displayValue = (string) $value;
                        } elseif ($definition['type'] === 'boolean') {
                            $displayValue = $this->booleanFromStored($value) ? '1' : '0';
                        } else {
                            $displayValue = (string) $value;
                        }
                    }
                } catch (Throwable $exception) {
                    $this->note($key, 'unreadable', $exception);
                    $problem = 'unreadable';
                    $source = array_key_exists($key, self::FAIL_CLOSED) ? 'safety_fallback' : 'environment';
                    $value = self::FAIL_CLOSED[$key] ?? config($definition['config']);
                    $configured = $this->hasValue($value, $definition['type']);
                    if (! $definition['secret'] && $value !== null) {
                        $displayValue = $definition['type'] === 'boolean'
                            ? ((bool) $value ? '1' : '0')
                            : (string) $value;
                    }
                }
            } else {
                $value = $databaseUnavailable
                    ? (self::FAIL_CLOSED[$key] ?? config($definition['config']))
                    : config($definition['config']);
                if ($databaseUnavailable && array_key_exists($key, self::FAIL_CLOSED)) {
                    $source = 'safety_fallback';
                }
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
                'problem' => $problem,
                'has_override' => $row !== null,
            ];
        }

        return $result;
    }

    private function note(string $key, string $status, ?Throwable $exception = null): void
    {
        if (isset($this->problems[$key])) {
            return;
        }

        $this->problems[$key] = $status;
        try {
            Log::warning('integration_settings.'.$status, [
                'key' => $key,
                'reason' => $exception !== null ? class_basename($exception) : 'not_numeric',
            ]);
        } catch (Throwable) {
            // Logging failures must not break first boot or expose the original
            // exception message. The redacted diagnostic remains queryable.
        }
    }

    /**
     * Recognised boolean strings stay those PHP already accepts.
     * Anything else, including "maybe", is invalid rather than false.
     */
    private function booleanFromStored(mixed $value): ?bool
    {
        if (! is_string($value) && ! is_int($value) && ! is_bool($value)) {
            return null;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        return is_bool($parsed) ? $parsed : null;
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
