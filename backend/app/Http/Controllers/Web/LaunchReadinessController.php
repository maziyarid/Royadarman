<?php

namespace App\Http\Controllers\Web;

use App\Domain\Identity\Enums\UserRole;
use App\Domain\Operations\Services\IntegrationSettings;
use App\Domain\Operations\Services\OperationalHealth;
use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\IntegrationSetting;
use App\Models\PolicyVersion;
use App\Models\User;
use App\Support\WorkspaceView;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class LaunchReadinessController extends Controller
{
    /** @var list<string> */
    private const MANUAL_ACKS = [
        'legal_approved',
        'sms_delivery_confirmed',
        'scanner_drill_passed',
        'backup_restore_rehearsed',
    ];

    /** @var list<string> */
    private const REQUIRED_POLICY_KEYS = [
        'case_coordination',
        'opg_document_sharing',
        'referral_sharing',
    ];

    public function index(Request $request, string $locale): View
    {
        $this->authorizeViewer($request);

        $acks = IntegrationSetting::query()
            ->whereIn('key', array_map(fn (string $key) => $this->ackKey($key), self::MANUAL_ACKS))
            ->get()
            ->keyBy('key');

        $policyCoverage = [];
        foreach (self::REQUIRED_POLICY_KEYS as $policyKey) {
            foreach (['fa', 'ar', 'en'] as $policyLocale) {
                $policyCoverage[$policyKey][$policyLocale] = PolicyVersion::query()
                    ->productionEligible()
                    ->where('policy_key', $policyKey)
                    ->where('locale', $policyLocale)
                    ->whereNotNull('published_at')
                    ->exists();
            }
        }

        $smsEndpoint = (string) config('royadarman.sms.tsms.endpoint');
        $smsHost = strtolower((string) parse_url($smsEndpoint, PHP_URL_HOST));
        $smsPath = (string) parse_url($smsEndpoint, PHP_URL_PATH);
        $smsSafe = (string) config('royadarman.sms.provider') === 'tsms'
            && strtolower((string) parse_url($smsEndpoint, PHP_URL_SCHEME)) === 'https'
            && in_array($smsHost, ['tsms.ir', 'www.tsms.ir'], true)
            && $smsPath === '/url/tsmshttp.php'
            && trim((string) config('royadarman.sms.tsms.username')) !== ''
            && trim((string) config('royadarman.sms.tsms.password')) !== ''
            && trim((string) config('royadarman.sms.tsms.from')) !== '';

        $activeCoordinators = User::query()
            ->where('role', UserRole::Coordinator->value)
            ->where('is_active', true)
            ->count();

        $verifiedClinicians = User::query()
            ->join('practitioners', 'practitioners.user_id', '=', 'users.id')
            ->where('users.role', UserRole::Clinician->value)
            ->where('users.is_active', true)
            ->where('practitioners.credential_status', 'verified')
            ->where(fn ($q) => $q->whereNull('practitioners.expires_at')->orWhere('practitioners.expires_at', '>', now()))
            ->count();

        $scannerCommand = (string) config('royadarman.opg.scanner.command');
        $scannerConfigured = (bool) config('royadarman.opg.scanner.enabled')
            && $scannerCommand !== ''
            && is_executable($scannerCommand);

        $retention = config('royadarman.retention.document_days');
        $retentionConfigured = is_numeric($retention) && (int) $retention > 0;

        $referralTtl = config('royadarman.referral.grant_ttl_minutes');
        $referralTtlConfigured = is_numeric($referralTtl) && (int) $referralTtl > 0;

        $retryAfter = (int) config('queue.connections.database.retry_after');
        $workerTimeout = (int) config('royadarman.queue.worker_timeout_seconds');
        $retryMargin = (int) config('royadarman.queue.retry_after_min_margin_seconds');
        $queueTimingSafe = $retryAfter - $workerTimeout >= $retryMargin;

        // Observed evidence, separate from the configuration checks above.
        $health = app(OperationalHealth::class)->snapshot();
        $integration = app(IntegrationSettings::class)->diagnostics();

        $allPoliciesPublished = collect($policyCoverage)
            ->flatten()
            ->every(fn (bool $published) => $published);

        $manual = [];
        foreach (self::MANUAL_ACKS as $key) {
            $row = $acks->get($this->ackKey($key));
            $manual[$key] = [
                'confirmed' => $row !== null,
                'confirmed_at' => $row?->updated_at,
            ];
        }

        $gates = [
            'environment' => [
                'ok' => config('app.env') === 'production' && config('app.debug') === false,
                'detail' => config('app.env').' / debug='.(config('app.debug') ? 'on' : 'off'),
            ],
            'sms_configuration' => ['ok' => $smsSafe, 'detail' => (string) config('royadarman.sms.provider')],
            'sms_delivery' => ['ok' => $manual['sms_delivery_confirmed']['confirmed'], 'detail' => null],
            'staffing_coordinator' => ['ok' => $activeCoordinators > 0, 'detail' => (string) $activeCoordinators],
            'staffing_clinician' => ['ok' => $verifiedClinicians > 0, 'detail' => (string) $verifiedClinicians],
            'scanner_configuration' => ['ok' => $scannerConfigured, 'detail' => $scannerCommand !== '' ? basename($scannerCommand) : null],
            'scanner_drill' => ['ok' => $manual['scanner_drill_passed']['confirmed'], 'detail' => null],
            'retention' => ['ok' => $retentionConfigured, 'detail' => $retentionConfigured ? (string) ((int) $retention) : null],
            'referral_ttl' => ['ok' => $referralTtlConfigured, 'detail' => $referralTtlConfigured ? (string) ((int) $referralTtl) : null],
            'policies' => ['ok' => $allPoliciesPublished, 'detail' => null],
            'legal_approval' => ['ok' => $manual['legal_approved']['confirmed'], 'detail' => null],
            'backup_restore' => ['ok' => $manual['backup_restore_rehearsed']['confirmed'], 'detail' => null],
            'queue_timing' => ['ok' => $queueTimingSafe, 'detail' => "{$workerTimeout}/{$retryAfter}"],
            'operational_backlog' => [
                'ok' => $health['state'] === 'ok',
                'detail' => $health['reasons'] === [] ? null : implode(', ', array_map(
                    fn (string $reason): string => __('panel.launch.health_reasons.'.$reason),
                    $health['reasons'],
                )),
            ],
            'integration_settings' => [
                'ok' => $integration['problem_count'] === 0,
                'detail' => $integration['problem_count'] === 0 ? null : (string) $integration['problem_count'],
            ],
        ];

        $ready = collect($gates)->every(fn (array $gate) => $gate['ok']);

        $release = null;
        $releasePath = storage_path('app/release-identity.json');
        if (is_file($releasePath)) {
            $decoded = json_decode((string) file_get_contents($releasePath), true);
            $release = is_array($decoded) ? $decoded : null;
        }

        return view('panel.launch-readiness.index', [
            ...WorkspaceView::data($request, 'launch_readiness'),
            'gates' => $gates,
            'manual' => $manual,
            'policyCoverage' => $policyCoverage,
            'ready' => $ready,
            'intakeEnabled' => (bool) config('royadarman.intake_enabled'),
            'release' => $release,
            'failedJobs' => $health['signals']['failed_jobs'] ?? null,
            'queuedJobs' => $health['signals']['queued_jobs'] ?? null,
            'operationalHealth' => $health,
        ])->with('locale', $locale);
    }

    public function acknowledge(Request $request, string $locale): RedirectResponse
    {
        $this->authorizeOwner($request);

        $data = $request->validate([
            'gate' => ['required', Rule::in(self::MANUAL_ACKS)],
            'confirmed' => ['required', 'boolean'],
        ]);

        $key = $this->ackKey($data['gate']);

        DB::transaction(function () use ($request, $data, $key): void {
            if ($data['confirmed']) {
                IntegrationSetting::query()->updateOrCreate(
                    ['key' => $key],
                    [
                        'value' => now()->toIso8601String(),
                        'updated_by_user_id' => $request->user()->id,
                    ],
                );
            } else {
                IntegrationSetting::query()->where('key', $key)->delete();
            }

            AuditEvent::query()->create([
                'actor_user_id' => $request->user()->id,
                'action' => 'launch_readiness.acknowledgement_changed',
                'resource_type' => 'launch_gate',
                'resource_id' => $data['gate'],
                'result' => 'success',
                'reason' => $data['confirmed'] ? 'confirmed' : 'revoked',
                'context' => ['source' => 'owner_launch_readiness'],
                'correlation_id' => (string) Str::ulid(),
                'created_at' => now(),
            ]);
        });

        return redirect()
            ->route('panel.launch-readiness.index', ['locale' => $locale])
            ->with('status', __('panel.saved'));
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

    private function authorizeOwner(Request $request): void
    {
        abort_unless(
            $request->user()?->is_active
            && $request->user()->role === UserRole::Owner
            && ! $request->session()->get('panel_demo', false),
            403,
        );
    }

    private function ackKey(string $gate): string
    {
        return 'launch_ack_'.$gate;
    }
}
