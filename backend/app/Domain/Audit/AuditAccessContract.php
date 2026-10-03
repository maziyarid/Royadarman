<?php

namespace App\Domain\Audit;

use App\Domain\Identity\Authorization\MembershipPermission;

/**
 * Proposed audit rules. assess() never authorises a write or a clinical read.
 * Existing writers are not routed through this class.
 */
final class AuditAccessContract
{
    public const STATUS = 'proposed_not_wired';

    /**
     * Columns on audit_events today. There is no tenant and no retention column.
     *
     * @return list<string>
     */
    public static function currentColumns(): array
    {
        return [
            'id',
            'actor_user_id',
            'action',
            'resource_type',
            'resource_id',
            'result',
            'reason',
            'context',
            'correlation_id',
            'created_at',
        ];
    }

    /**
     * @return list<string>
     */
    public static function namedResults(): array
    {
        return ['success', 'failure', 'denied'];
    }

    public static function acceptedRetentionDays(): ?int
    {
        return null;
    }

    public static function authorisesClinicalRead(string $title): MembershipPermission
    {
        return new MembershipPermission(false, 'privileged_read_not_authorised');
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function assess(array $row): MembershipPermission
    {
        if (array_key_exists('retention_days', $row)) {
            return new MembershipPermission(false, 'retention_not_decided');
        }

        if (array_key_exists('clinic_id', $row) || array_key_exists('tenant_id', $row)) {
            return new MembershipPermission(false, 'tenant_column_absent');
        }

        $action = $row['action'] ?? null;
        if (! is_string($action) || $action === '') {
            return new MembershipPermission(false, 'action_required');
        }

        $correlation = $row['correlation_id'] ?? null;
        if (! is_string($correlation) || $correlation === '') {
            return new MembershipPermission(false, 'correlation_required');
        }

        $result = $row['result'] ?? null;
        if (! is_string($result) || ! in_array($result, self::namedResults(), true)) {
            return new MembershipPermission(false, 'result_unspecified');
        }

        $context = $row['context'] ?? [];
        if (is_string($context)) {
            return new MembershipPermission(false, 'context_must_be_structured');
        }

        if (! is_array($context)) {
            return new MembershipPermission(false, 'context_must_be_structured');
        }

        $forbidden = self::forbiddenReason($row['reason'] ?? null, $context);
        if ($forbidden !== null) {
            return new MembershipPermission(false, $forbidden);
        }

        return new MembershipPermission(false, 'redaction_passed_not_a_writer');
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function forbiddenReason(mixed $reason, array $context): ?string
    {
        $found = self::scan($reason);
        if ($found !== null) {
            return $found;
        }

        return self::scan($context);
    }

    private static function scan(mixed $value): ?string
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                if (is_string($key) && self::forbiddenKey($key)) {
                    return 'forbidden_field';
                }

                $nested = self::scan($item);
                if ($nested !== null) {
                    return $nested;
                }
            }

            return null;
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        if (str_contains($value, '@')) {
            return 'email_not_allowed';
        }

        $digits = preg_replace('/[\s-]/', '', $value) ?? $value;
        if (preg_match('/^(?:\+98|0)9\d{9}$/', $digits) === 1) {
            return 'phone_not_allowed';
        }

        return null;
    }

    private static function forbiddenKey(string $key): bool
    {
        return in_array(strtolower($key), [
            'phone',
            'mobile',
            'patient_mobile',
            'patient_name',
            'password',
            'token',
            'secret',
            'otp',
            'recovery_code',
            'document_body',
            'observations',
            'message_body',
            'email',
            'licence_number',
            'license_number',
        ], true);
    }
}
