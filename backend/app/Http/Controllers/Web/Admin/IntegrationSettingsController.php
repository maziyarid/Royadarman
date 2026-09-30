<?php

namespace App\Http\Controllers\Web\Admin;

use App\Domain\Identity\Enums\UserRole;
use App\Domain\Operations\Services\IntegrationSettings;
use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\IntegrationSetting;
use App\Support\WorkspaceView;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class IntegrationSettingsController extends Controller
{
    public function index(Request $request, string $locale, IntegrationSettings $settings): View
    {
        $this->authorizeAdmin($request);

        return view('panel.integrations.index', [
            ...WorkspaceView::data($request, 'integrations'),
            'settings' => $settings->forDisplay(),
            'definitions' => IntegrationSettings::definitions(),
            'canEditOperations' => $request->user()->role === UserRole::Owner,
        ])->with('locale', $locale);
    }

    public function update(Request $request, string $locale, IntegrationSettings $settings): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $definitions = IntegrationSettings::definitions();
        $clearable = array_keys(array_filter(
            $definitions,
            fn (array $definition): bool => $definition['group'] !== 'launch',
        ));
        $operationalKeys = ['retention_document_days', 'referral_grant_ttl_minutes', 'scanner_enabled'];

        if ($request->user()->role !== UserRole::Owner) {
            $attemptedOperationalChange = collect($operationalKeys)->contains(
                fn (string $key): bool => $request->has($key),
            ) || collect((array) $request->input('clear', []))->intersect($operationalKeys)->isNotEmpty();

            abort_if($attemptedOperationalChange, 403);
        }

        $rules = [
            'clear' => ['nullable', 'array'],
            'clear.*' => [Rule::in($clearable)],
            'neshan_map_api_key' => ['nullable', 'string', 'max:500'],
            'neshan_service_api_key' => ['nullable', 'string', 'max:500'],
            'sms_provider' => ['required', Rule::in(['tsms', 'http'])],
            'sms_callbacks_enabled' => ['required', 'boolean'],
            'sms_callback_secret' => ['nullable', 'string', 'max:500'],
            'sms_endpoint' => ['nullable', 'url', 'starts_with:https://', 'max:1000'],
            'sms_token' => ['nullable', 'string', 'max:1000'],
            'tsms_endpoint' => ['nullable', 'url', 'starts_with:https://', 'max:1000'],
            'tsms_username' => ['nullable', 'string', 'max:255'],
            'tsms_password' => ['nullable', 'string', 'max:500'],
            'tsms_from' => ['nullable', 'string', 'max:100'],
            'resend_api_key' => ['nullable', 'string', 'max:500'],
            'retention_document_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'referral_grant_ttl_minutes' => ['nullable', 'integer', 'min:5', 'max:43200'],
            'scanner_enabled' => ['nullable', 'boolean'],
        ];

        $data = $request->validate($rules);
        $clear = array_values(array_unique($data['clear'] ?? []));
        $actorId = (int) $request->user()->id;
        $changed = [];

        DB::transaction(function () use ($definitions, $data, $clear, $actorId, &$changed): void {
            foreach ($definitions as $key => $definition) {
                if (in_array($key, $clear, true)) {
                    if (IntegrationSetting::query()->where('key', $key)->delete() > 0) {
                        $changed[] = [$key, 'cleared'];
                    }

                    continue;
                }

                if (! array_key_exists($key, $data)) {
                    continue;
                }

                $value = $data[$key];

                if ($definition['secret'] && ($value === null || $value === '')) {
                    // Blank secret means keep the existing DB override/fallback.
                    continue;
                }

                if (! $definition['secret'] && $definition['type'] !== 'boolean' && ($value === null || $value === '')) {
                    // Blank non-secret field removes only the DB override, falling
                    // back to the environment setting.
                    if (IntegrationSetting::query()->where('key', $key)->delete() > 0) {
                        $changed[] = [$key, 'cleared'];
                    }

                    continue;
                }

                $stored = $definition['type'] === 'boolean'
                    ? (filter_var($value, FILTER_VALIDATE_BOOL) ? '1' : '0')
                    : trim((string) $value);

                IntegrationSetting::query()->updateOrCreate(
                    ['key' => $key],
                    ['value' => $stored, 'updated_by_user_id' => $actorId],
                );

                $changed[] = [$key, 'updated'];
            }

            foreach ($changed as [$key, $action]) {
                AuditEvent::query()->create([
                    'actor_user_id' => $actorId,
                    'action' => 'integration.setting.'.$action,
                    'resource_type' => 'integration_setting',
                    'resource_id' => $key,
                    'result' => 'success',
                    'reason' => null,
                    'context' => ['source' => 'admin_panel'],
                    'correlation_id' => (string) Str::ulid(),
                    'created_at' => now(),
                ]);
            }
        });

        $settings->applyToRuntimeConfig();

        return redirect()
            ->route('integrations.index', ['locale' => $locale])
            ->with('status', __('integrations.saved', ['count' => count($changed)]));
    }

    private function authorizeAdmin(Request $request): void
    {
        $user = $request->user();

        abort_unless(
            $user?->is_active
            && in_array($user->role, [UserRole::Owner, UserRole::TechnicalAdministrator], true)
            && ! $request->session()->get('panel_demo', false),
            403,
        );
    }
}
