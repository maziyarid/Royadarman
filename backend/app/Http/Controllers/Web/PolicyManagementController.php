<?php

namespace App\Http\Controllers\Web;

use App\Domain\Identity\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\IntegrationSetting;
use App\Models\PolicyVersion;
use App\Support\WorkspaceView;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class PolicyManagementController extends Controller
{
    private const POLICY_KEYS = [
        'case_coordination',
        'opg_document_sharing',
        'referral_sharing',
    ];

    private const LOCALES = ['fa', 'ar', 'en'];

    public function index(Request $request, string $locale): View
    {
        $this->authorizeViewer($request);

        $rows = PolicyVersion::query()
            ->productionEligible()
            ->whereIn('policy_key', self::POLICY_KEYS)
            ->whereIn('locale', self::LOCALES)
            ->orderBy('policy_key')
            ->orderBy('locale')
            ->orderByDesc('created_at')
            ->get()
            ->groupBy(fn (PolicyVersion $policy) => $policy->policy_key.'|'.$policy->locale);

        $matrix = [];
        foreach (self::POLICY_KEYS as $key) {
            foreach (self::LOCALES as $policyLocale) {
                $versions = $rows->get($key.'|'.$policyLocale, collect());
                $matrix[$key][$policyLocale] = [
                    'published' => $versions->first(fn (PolicyVersion $policy) => $policy->published_at !== null),
                    'drafts' => $versions->filter(fn (PolicyVersion $policy) => $policy->published_at === null)->values(),
                ];
            }
        }

        return view('panel.policies.index', [
            ...WorkspaceView::data($request, 'policies'),
            'matrix' => $matrix,
            'policyKeys' => self::POLICY_KEYS,
            'policyLocales' => self::LOCALES,
            'canPublish' => $request->user()->role === UserRole::Owner,
            'legalApproved' => IntegrationSetting::query()->where('key', 'launch_ack_legal_approved')->exists(),
        ])->with('locale', $locale);
    }

    public function store(Request $request, string $locale): RedirectResponse
    {
        $this->authorizeEditor($request);

        $data = $this->validatePolicy($request);

        $policy = PolicyVersion::query()->create([
            'policy_key' => $data['policy_key'],
            'version' => $data['version'],
            'locale' => $data['locale'],
            'content' => $data['content'],
            'content_hash' => hash('sha256', $data['content']),
            'published_at' => null,
        ]);

        $this->audit($request, 'policy.draft.created', $policy);

        return back()->with('status', __('panel.saved'));
    }

    public function update(Request $request, string $locale, PolicyVersion $policy): RedirectResponse
    {
        $this->authorizeEditor($request);
        abort_if($policy->published_at !== null, 409);

        $data = $this->validatePolicy($request, $policy);

        $policy->update([
            'policy_key' => $data['policy_key'],
            'version' => $data['version'],
            'locale' => $data['locale'],
            'content' => $data['content'],
            'content_hash' => hash('sha256', $data['content']),
        ]);

        $this->audit($request, 'policy.draft.updated', $policy);

        return back()->with('status', __('panel.saved'));
    }

    public function destroy(Request $request, string $locale, PolicyVersion $policy): RedirectResponse
    {
        $this->authorizeEditor($request);
        abort_if($policy->published_at !== null, 409);

        $id = (string) $policy->id;
        $context = ['policy_key' => $policy->policy_key, 'locale' => $policy->locale, 'version' => $policy->version];
        $policy->delete();

        AuditEvent::query()->create([
            'actor_user_id' => $request->user()->id,
            'action' => 'policy.draft.deleted',
            'resource_type' => 'policy_version',
            'resource_id' => $id,
            'result' => 'success',
            'reason' => null,
            'context' => $context,
            'correlation_id' => (string) Str::ulid(),
            'created_at' => now(),
        ]);

        return back()->with('status', __('panel.saved'));
    }

    public function publish(Request $request, string $locale, PolicyVersion $policy): RedirectResponse
    {
        $this->authorizeOwner($request);
        abort_if($policy->published_at !== null, 409);

        abort_unless(
            IntegrationSetting::query()->where('key', 'launch_ack_legal_approved')->exists(),
            423,
            __('panel.policies.legal_required'),
        );

        DB::transaction(function () use ($request, $policy): void {
            $policy->update(['published_at' => now()]);
            $this->audit($request, 'policy.published', $policy);
        });

        return back()->with('status', __('panel.policies.published_notice'));
    }

    private function validatePolicy(Request $request, ?PolicyVersion $policy = null): array
    {
        return $request->validate([
            'policy_key' => ['required', Rule::in(self::POLICY_KEYS)],
            'version' => [
                'required',
                'string',
                'max:50',
                Rule::unique('policy_versions', 'version')
                    ->where(fn ($q) => $q
                        ->where('policy_key', $request->input('policy_key'))
                        ->where('locale', $request->input('locale')))
                    ->ignore($policy?->id),
            ],
            'locale' => ['required', Rule::in(self::LOCALES)],
            'content' => ['required', 'string', 'min:50', 'max:50000'],
        ]);
    }

    private function authorizeViewer(Request $request): void
    {
        abort_unless(
            $request->user()?->is_active
            && in_array($request->user()->role, [UserRole::Owner, UserRole::TechnicalAdministrator], true)
            && ! $request->session()->get('panel_demo', false),
            403,
        );
    }

    private function authorizeEditor(Request $request): void
    {
        $this->authorizeViewer($request);
    }

    private function authorizeOwner(Request $request): void
    {
        abort_unless(
            $request->user()?->is_active
            && $request->user()->role === UserRole::Owner
            && ! $request->session()->get('panel_demo', false),
            403,
        );
    }

    private function audit(Request $request, string $action, PolicyVersion $policy): void
    {
        AuditEvent::query()->create([
            'actor_user_id' => $request->user()->id,
            'action' => $action,
            'resource_type' => 'policy_version',
            'resource_id' => (string) $policy->id,
            'result' => 'success',
            'reason' => null,
            'context' => [
                'policy_key' => $policy->policy_key,
                'locale' => $policy->locale,
                'version' => $policy->version,
                'content_hash' => $policy->content_hash,
            ],
            'correlation_id' => (string) Str::ulid(),
            'created_at' => now(),
        ]);
    }
}
