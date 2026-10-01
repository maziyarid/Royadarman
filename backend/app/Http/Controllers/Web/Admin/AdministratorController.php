<?php

namespace App\Http\Controllers\Web\Admin;

use App\Domain\Identity\Enums\UserRole;
use App\Domain\Identity\Services\SessionInventoryService;
use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\User;
use App\Support\DigitNormalizer;
use App\Support\PhoneHasher;
use App\Support\WorkspaceView;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class AdministratorController extends Controller
{
    private const ROLES = [
        UserRole::Owner,
        UserRole::TechnicalAdministrator,
        UserRole::Coordinator,
        UserRole::Clinician,
        UserRole::ClinicRepresentative,
        UserRole::Superadmin,
        UserRole::Developer,
        UserRole::Supervisor,
        UserRole::Receptionist,
        UserRole::Accountant,
        UserRole::CustomerSupport,
        UserRole::ClinicManager,
    ];

    public function __construct(
        private readonly PhoneHasher $phoneHasher,
        private readonly SessionInventoryService $sessions,
    ) {}

    public function index(Request $request, string $locale): View
    {
        $this->authorizeOwner($request);

        $staff = User::query()
            ->whereIn('role', array_map(static fn (UserRole $role): string => $role->value, self::ROLES))
            ->orderByRaw("FIELD(role, 'owner','superadmin','tech_admin','developer','coordinator','supervisor','customer_support','clinician','clinic_rep','clinic_manager','receptionist','accountant')")
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name ?: __('administrators.unnamed'),
                'role' => $user->role->value,
                'locale' => $user->locale,
                'active' => $user->is_active,
                'phone' => $this->maskPhone($user->phone),
                'mfa' => filled($user->totp_secret),
                'demo' => $this->isDemo($user),
                'current' => $user->is($request->user()),
                'last_authenticated_at' => $user->last_authenticated_at,
            ]);

        return view('panel.administrators.index', [
            ...WorkspaceView::data($request, 'administrators'),
            'staff' => $staff,
            'roles' => self::ROLES,
        ])->with('locale', $locale);
    }

    public function store(Request $request, string $locale): RedirectResponse
    {
        $this->authorizeOwner($request);

        $data = $request->validate([
            'mobile' => ['required', 'string', 'max:32'],
            'name' => ['required', 'string', 'max:80'],
            'role' => ['required', Rule::in(array_map(static fn (UserRole $role): string => $role->value, self::ROLES))],
            'locale' => ['required', Rule::in(['fa', 'ar', 'en'])],
        ]);

        $mobile = DigitNormalizer::iranianMobile($data['mobile']);

        if (! preg_match('/^09\d{9}$/', $mobile)) {
            throw ValidationException::withMessages(['mobile' => __('ui.errors.mobile')]);
        }

        $phoneHash = $this->phoneHasher->hash($mobile);
        $existing = User::query()->where('phone_hash', $phoneHash)->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'mobile' => __('administrators.errors.mobile_exists'),
            ]);
        }

        $user = DB::transaction(function () use ($data, $mobile, $phoneHash, $request): User {
            $user = User::query()->create([
                'name' => trim($data['name']),
                'phone' => $mobile,
                'phone_hash' => $phoneHash,
                'password' => null,
                'role' => $data['role'],
                'locale' => $data['locale'],
                'totp_secret' => null,
                'mfa_recovery_codes' => null,
                'is_active' => true,
            ]);

            $this->audit(
                (int) $request->user()->id,
                'staff.created',
                $user,
                ['role' => $data['role'], 'locale' => $data['locale']],
            );

            return $user;
        });

        return redirect()
            ->route('administrators.index', ['locale' => $locale])
            ->with('status', __('administrators.created', ['name' => $user->name]));
    }

    public function update(Request $request, string $locale, User $user): RedirectResponse
    {
        $this->authorizeOwner($request);
        $this->guardMutable($request, $user);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'role' => ['required', Rule::in(array_map(static fn (UserRole $role): string => $role->value, self::ROLES))],
            'locale' => ['required', Rule::in(['fa', 'ar', 'en'])],
            'is_active' => ['required', 'boolean'],
        ]);

        if ($user->role === UserRole::Owner
            && ($data['role'] !== UserRole::Owner->value || ! (bool) $data['is_active'])
            && $this->activeRealOwnerCount() <= 1) {
            throw ValidationException::withMessages([
                'role' => __('administrators.errors.last_owner'),
            ]);
        }

        $before = [
            'role' => $user->role->value,
            'locale' => $user->locale,
            'active' => $user->is_active,
        ];

        DB::transaction(function () use ($data, $request, $user, $before): void {
            $securityChanged = $user->role->value !== $data['role']
                || $user->is_active !== (bool) $data['is_active'];

            if ($securityChanged) {
                $this->sessions->forceRevokeAll($user, $request->user(), 'staff_admin_update');
            }

            $user->update([
                'name' => trim($data['name']),
                'role' => $data['role'],
                'locale' => $data['locale'],
                'is_active' => (bool) $data['is_active'],
            ]);

            $this->audit(
                (int) $request->user()->id,
                'staff.updated',
                $user,
                [
                    'before' => $before,
                    'after' => [
                        'role' => $data['role'],
                        'locale' => $data['locale'],
                        'active' => (bool) $data['is_active'],
                    ],
                ],
            );
        });

        return redirect()
            ->route('administrators.index', ['locale' => $locale])
            ->with('status', __('administrators.updated'));
    }

    public function resetMfa(Request $request, string $locale, User $user): RedirectResponse
    {
        $this->authorizeOwner($request);
        $this->guardMutable($request, $user);

        DB::transaction(function () use ($request, $user): void {
            $this->sessions->forceRevokeAll($user, $request->user(), 'staff_mfa_reset');

            $user->update([
                'totp_secret' => null,
                'mfa_recovery_codes' => null,
            ]);

            $this->audit(
                (int) $request->user()->id,
                'staff.mfa_reset',
                $user,
                [],
            );
        });

        return redirect()
            ->route('administrators.index', ['locale' => $locale])
            ->with('status', __('administrators.mfa_reset'));
    }

    public function revokeSessions(Request $request, string $locale, User $user): RedirectResponse
    {
        $this->authorizeOwner($request);
        $this->guardMutable($request, $user);

        $count = $this->sessions->forceRevokeAll($user, $request->user(), 'staff_admin_revoke');

        return redirect()
            ->route('administrators.index', ['locale' => $locale])
            ->with('status', __('administrators.sessions_revoked', ['count' => $count]));
    }

    private function authorizeOwner(Request $request): void
    {
        abort_unless(
            $request->user()?->role === UserRole::Owner
            && $request->user()->is_active
            && ! $request->session()->get('panel_demo', false),
            403,
        );
    }

    private function guardMutable(Request $request, User $user): void
    {
        abort_if($this->isDemo($user), 403, 'Reserved demonstration identities cannot be mutated.');
        abort_if($request->user()->is($user), 422, __('administrators.errors.self_change'));
        abort_unless($user->role->isStaff(), 404);
    }

    private function isDemo(User $user): bool
    {
        return is_string($user->email) && str_ends_with($user->email, '@royadarman.invalid');
    }

    private function activeRealOwnerCount(): int
    {
        return User::query()
            ->where('role', UserRole::Owner->value)
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('email')->orWhere('email', 'not like', '%@royadarman.invalid'))
            ->count();
    }

    private function maskPhone(?string $phone): string
    {
        $phone = (string) $phone;

        if (strlen($phone) < 7) {
            return '—';
        }

        return substr($phone, 0, 4).'***'.substr($phone, -4);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function audit(int $actorId, string $action, User $subject, array $context): void
    {
        AuditEvent::query()->create([
            'actor_user_id' => $actorId,
            'action' => $action,
            'resource_type' => 'user',
            'resource_id' => (string) $subject->id,
            'result' => 'success',
            'reason' => null,
            'context' => $context,
            'correlation_id' => (string) Str::ulid(),
            'created_at' => now(),
        ]);
    }
}
