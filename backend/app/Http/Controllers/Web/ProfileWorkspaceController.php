<?php

namespace App\Http\Controllers\Web;

use App\Domain\Identity\Services\SessionInventoryService;
use App\Domain\Identity\Services\StaffMfaService;
use App\Http\Controllers\Controller;
use App\Support\WorkspaceView;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class ProfileWorkspaceController extends Controller
{
    public function __construct(
        private readonly SessionInventoryService $sessions,
        private readonly StaffMfaService $mfa,
    ) {}

    public function show(Request $request): View
    {
        $currentId = $request->hasSession() ? (string) $request->session()->getId() : null;
        $sessionRows = $this->sessions->listForUser($request->user(), $currentId);

        $pendingTotpSecret = (string) $request->session()->get('pending_totp_secret', '');

        return view('panel.profile', [
            ...WorkspaceView::data($request, 'profile'),
            'profileUser' => $request->user(),
            'credentialsConfigured' => filled($request->user()->username) && filled($request->user()->password),
            'sessions' => $sessionRows,
            'mfaConfigured' => $this->mfa->isConfigured($request->user()),
            'pendingTotpSecret' => $pendingTotpSecret,
            'pendingTotpUri' => $pendingTotpSecret !== ''
                ? $this->mfa->otpauthUri($request->user(), $pendingTotpSecret)
                : null,
            'newRecoveryCodes' => $request->session()->get('new_recovery_codes', []),
            'passkeys' => $request->user()->passkeys()
                ->latest('created_at')
                ->get(['id', 'name', 'last_used_at', 'created_at']),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'locale' => ['required', 'in:fa,ar,en'],
            'name' => ['nullable', 'string', 'max:80'],
        ]);

        $request->user()->update($data);

        return redirect()
            ->route('panel.profile', ['locale' => $data['locale']])
            ->with('status', __('panel.saved'));
    }

    public function updateCredentials(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless(filled($user->phone_hash) && ! $request->session()->get('panel_demo', false), 403);
        $request->merge(['username' => mb_strtolower(trim((string) $request->input('username')))]);

        $data = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:32', 'regex:/\A[a-zA-Z0-9][a-zA-Z0-9._-]{2,31}\z/', \Illuminate\Validation\Rule::unique('users', 'username')->ignore($user->id)],
            'password' => ['required', 'string', 'min:12', 'max:128', 'confirmed'],
        ]);

        $otpRecovery = $request->session()->get('auth_method') === 'otp';
        if (filled($user->password) && ! $otpRecovery && ! \Illuminate\Support\Facades\Hash::check((string) $request->input('current_password'), (string) $user->password)) {
            throw ValidationException::withMessages(['current_password' => __('panel.credentials.current_password_invalid')]);
        }

        $user->update([
            'username' => mb_strtolower($data['username']),
            'password' => $data['password'],
        ]);

        $currentId = (string) $request->session()->getId();
        $this->sessions->revokeOthers($user, $currentId, $user->id, 'credentials_changed');
        $request->session()->put('auth_method', 'credentials_updated');

        return redirect()
            ->route('panel.profile', ['locale' => app()->getLocale()])
            ->with('status', __('panel.credentials.saved'));
    }

    public function startTotp(Request $request): RedirectResponse
    {
        $this->guardSecurityMutation($request);

        if ($this->mfa->isConfigured($request->user())) {
            return redirect()
                ->route('panel.profile', ['locale' => app()->getLocale()])
                ->with('status', __('panel.security.totp_already_enabled'));
        }

        $request->session()->put('pending_totp_secret', $this->mfa->generateSecret());

        return redirect()
            ->route('panel.profile', ['locale' => app()->getLocale()])
            ->with('status', __('panel.security.totp_started'));
    }

    public function confirmTotp(Request $request): RedirectResponse
    {
        $this->guardSecurityMutation($request);

        $data = $request->validate([
            'totp_code' => ['required', 'string', 'size:6'],
        ]);
        $secret = (string) $request->session()->get('pending_totp_secret', '');

        if ($secret === '' || ! $this->mfa->verifySecret($secret, $data['totp_code'])) {
            throw ValidationException::withMessages([
                'totp_code' => __('panel.security.totp_invalid'),
            ]);
        }

        $codes = $this->mfa->generateRecoveryCodes();
        $request->user()->update([
            'totp_secret' => $secret,
            'mfa_recovery_codes' => $this->mfa->hashRecoveryCodes($codes),
        ]);

        $currentId = (string) $request->session()->getId();
        $this->sessions->revokeOthers(
            $request->user(),
            $currentId,
            $request->user()->id,
            'mfa_enabled',
        );

        $request->session()->forget('pending_totp_secret');

        return redirect()
            ->route('panel.profile', ['locale' => app()->getLocale()])
            ->with('status', __('panel.security.totp_enabled'))
            ->with('new_recovery_codes', $codes);
    }

    public function cancelTotp(Request $request): RedirectResponse
    {
        $this->guardSecurityMutation($request);
        $request->session()->forget('pending_totp_secret');

        return redirect()
            ->route('panel.profile', ['locale' => app()->getLocale()]);
    }

    public function disableTotp(Request $request): RedirectResponse
    {
        $this->guardSecurityMutation($request);

        $data = $request->validate([
            'totp_code' => ['nullable', 'string', 'size:6'],
            'recovery_code' => ['nullable', 'string', 'max:100'],
        ]);

        if (! $this->mfa->isConfigured($request->user())
            || ! $this->mfa->verifyAndConsume(
                $request->user(),
                $data['totp_code'] ?? null,
                $data['recovery_code'] ?? null,
            )) {
            throw ValidationException::withMessages([
                'totp_code' => __('panel.security.totp_invalid'),
            ]);
        }

        $request->user()->update([
            'totp_secret' => null,
            'mfa_recovery_codes' => null,
        ]);

        $currentId = (string) $request->session()->getId();
        $this->sessions->revokeOthers(
            $request->user(),
            $currentId,
            $request->user()->id,
            'mfa_disabled',
        );

        return redirect()
            ->route('panel.profile', ['locale' => app()->getLocale()])
            ->with('status', __('panel.security.totp_disabled'));
    }

    private function guardSecurityMutation(Request $request): void
    {
        abort_unless(
            $request->user()?->role->isStaff()
            && ! $request->session()->get('panel_demo', false),
            403,
        );
    }

    public function revokeSession(Request $request, string $session): RedirectResponse
    {
        $user = $request->user();
        $currentId = (string) $request->session()->getId();

        if (hash_equals($currentId, $session)) {
            return redirect()
                ->route('panel.profile', ['locale' => app()->getLocale()])
                ->with('status', __('panel.sessions.cannot_revoke_current'));
        }

        $this->sessions->revokeOne($user, $session);

        return redirect()
            ->route('panel.profile', ['locale' => app()->getLocale()])
            ->with('status', __('panel.sessions.revoked_one'));
    }

    public function revokeOthers(Request $request): RedirectResponse
    {
        $user = $request->user();
        $currentId = (string) $request->session()->getId();
        $deleted = $this->sessions->revokeOthers($user, $currentId);

        return redirect()
            ->route('panel.profile', ['locale' => app()->getLocale()])
            ->with('status', __('panel.sessions.revoked_others', ['count' => $deleted]));
    }

    public function revokeAll(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->sessions->revokeAll($user, $user->id, 'user_revoke_all');

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $locale = app()->getLocale();
        $home = $locale === 'fa' ? route('public.home.fa') : route('public.home', ['locale' => $locale]);

        return redirect($home)->with('status', __('panel.sessions.revoked_all'));
    }
}
