<?php

namespace App\Console\Commands;

use App\Domain\Identity\Enums\UserRole;
use App\Domain\Identity\Services\SessionInventoryService;
use App\Models\User;
use App\Support\DigitNormalizer;
use App\Support\PhoneHasher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class ProvisionStaff extends Command
{
    protected $signature = 'royadarman:staff:provision
        {mobile : Iranian mobile number}
        {role : coordinator|clinician|clinic_rep|owner|tech_admin}
        {--name= : Display name}
        {--locale=fa : fa|ar|en}
        {--replace-mfa : Replace existing TOTP secret and recovery codes}
        {--force-role-change : Allow changing an existing staff role}';

    protected $description = 'Provision a Royadarman staff identity with TOTP MFA and one-time recovery codes.';

    public function handle(PhoneHasher $phoneHasher, SessionInventoryService $sessions): int
    {
        $mobile = DigitNormalizer::iranianMobile((string) $this->argument('mobile'));
        $roleValue = (string) $this->argument('role');
        $locale = (string) $this->option('locale');
        $allowedRoles = [
            UserRole::Coordinator,
            UserRole::Clinician,
            UserRole::ClinicRepresentative,
            UserRole::Owner,
            UserRole::TechnicalAdministrator,
        ];
        $role = collect($allowedRoles)->first(fn (UserRole $candidate) => $candidate->value === $roleValue);

        if (! preg_match('/^09\d{9}$/', $mobile)) {
            $this->error('Invalid Iranian mobile number.');

            return self::FAILURE;
        }
        if (! $role instanceof UserRole) {
            $this->error('Invalid staff role.');

            return self::FAILURE;
        }
        if (! in_array($locale, ['fa', 'ar', 'en'], true)) {
            $this->error('Invalid locale.');

            return self::FAILURE;
        }

        $phoneHash = $phoneHasher->hash($mobile);
        $user = User::query()->where('phone_hash', $phoneHash)->first();

        if ($user && $user->role === UserRole::Patient) {
            $hasCases = DB::table('patient_cases')->where('patient_user_id', $user->id)->exists();
            if ($hasCases) {
                $this->error('Refusing to convert a patient identity with existing cases into a staff identity. Use a separate staff mobile number.');

                return self::FAILURE;
            }
        }

        if ($user && $user->role->isStaff() && $user->role !== $role && ! (bool) $this->option('force-role-change')) {
            $this->error('Staff identity already has role '.$user->role->value.'. Use --force-role-change for an intentional role change.');

            return self::FAILURE;
        }

        $created = false;
        $roleChanged = false;
        $reactivated = false;
        if (! $user) {
            $user = User::query()->create([
                'phone' => $mobile,
                'phone_hash' => $phoneHash,
                'name' => $this->option('name') ?: null,
                'role' => $role,
                'locale' => $locale,
                'is_active' => true,
            ]);
            $created = true;
        } else {
            $roleChanged = $user->role !== $role;
            $reactivated = ! $user->is_active;
        }

        $needsMfa = $created || ! $user->totp_secret || (bool) $this->option('replace-mfa');

        $secret = null;
        $plainRecoveryCodes = [];
        $hashedRecoveryCodes = [];
        if ($needsMfa) {
            $secret = $this->base32(random_bytes(20));
            for ($i = 0; $i < 8; $i++) {
                $code = strtoupper(Str::random(5).'-'.Str::random(5));
                $plainRecoveryCodes[] = $code;
                $hashedRecoveryCodes[] = Hash::make($code);
            }
        }

        if ($created) {
            if ($needsMfa) {
                $user->update([
                    'totp_secret' => $secret,
                    'mfa_recovery_codes' => $hashedRecoveryCodes,
                ]);
            }
        } else {
            $attributes = [
                'name' => $this->option('name') ?: $user->name,
                'role' => $role,
                'locale' => $locale,
                'is_active' => true,
            ];
            if ($needsMfa) {
                $attributes['totp_secret'] = $secret;
                $attributes['mfa_recovery_codes'] = $hashedRecoveryCodes;
            }

            $shouldRevoke = $roleChanged || $needsMfa || $reactivated;
            $reason = match (true) {
                $roleChanged && $needsMfa => 'staff_role_change_and_mfa',
                $roleChanged => 'staff_role_change',
                $needsMfa => 'staff_mfa_replaced',
                $reactivated => 'staff_reactivated',
                default => 'staff_identity_update',
            };

            $this->commitIdentityChangeAndRevoke($user, $attributes, $sessions, $shouldRevoke, $reason);
        }

        if (! $needsMfa) {
            $this->info('Staff identity is active and already has MFA configured. No secret was displayed or changed.');

            return self::SUCCESS;
        }

        $label = rawurlencode('Royadarman:'.$mobile);
        $issuer = rawurlencode('Royadarman');
        $this->warn('Display these MFA values only to the intended staff member now. They will not be recoverable from this command.');
        $this->line('TOTP secret: '.$secret);
        $this->line('Authenticator URI: otpauth://totp/'.$label.'?secret='.$secret.'&issuer='.$issuer.'&digits=6&period=30');
        $this->line('Recovery codes:');
        foreach ($plainRecoveryCodes as $code) {
            $this->line('  '.$code);
        }

        return self::SUCCESS;
    }

    /**
     * Persist a sensitive identity/MFA/reactivation change with session revocation.
     *
     * Revoke first inside the user/audit connection transaction so a same-connection
     * audit failure cannot restore sessions after the role/MFA/active row has already
     * been committed. Split SESSION_CONNECTION stores still delete first (no XA);
     * identity then commits on the application connection.
     *
     * Inactive→active is a sensitive identity transition: dormant session rows can
     * survive EnsureActiveUser (which only invalidates a request that actually
     * presents an inactive session).
     *
     * @param  array<string, mixed>  $attributes
     */
    private function commitIdentityChangeAndRevoke(
        User $user,
        array $attributes,
        SessionInventoryService $sessions,
        bool $shouldRevoke,
        string $reason,
    ): void {
        $connection = $user->getConnectionName() ?? (string) config('database.default');

        DB::connection($connection)->transaction(function () use ($user, $attributes, $sessions, $shouldRevoke, $reason): void {
            if ($shouldRevoke) {
                $this->revokeSessionsAfterSecurityChange($sessions, $user, $reason);
            }
            $user->update($attributes);
        });
    }

    private function revokeSessionsAfterSecurityChange(SessionInventoryService $sessions, User $user, string $reason): void
    {
        $deleted = $sessions->revokeAll($user, null, $reason);
        if ($deleted > 0) {
            $this->info("Revoked {$deleted} existing session(s) after identity/security change ({$reason}).");
        }
    }

    private function base32(string $binary): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split($binary) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }
        $encoded = '';
        foreach (str_split($bits, 5) as $chunk) {
            if (strlen($chunk) < 5) {
                $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            }
            $encoded .= $alphabet[bindec($chunk)];
        }

        return $encoded;
    }
}
