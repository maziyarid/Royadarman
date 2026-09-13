<?php

namespace App\Console\Commands;

use App\Models\AuditEvent;
use App\Models\CaseAssignment;
use App\Models\Clinic;
use App\Models\ClinicMembership;
use App\Models\ConsentEvent;
use App\Models\PatientCase;
use App\Models\PolicyVersion;
use App\Models\Practitioner;
use App\Models\ReferralGrant;
use App\Models\ReferralProposal;
use App\Models\ReviewRevision;
use App\Models\User;
use App\Support\PanelDemoRegistry;
use App\Support\PhoneHasher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SeedPanelDemo extends Command
{
    protected $signature = 'royadarman:panel-demo:seed
        {--force : Allow controlled seeding in production after PANEL_DEMO_ACCESS=true}';

    protected $description = 'Seed synthetic TEST-only data for signed Royadarman role-panel demonstrations.';

    public function handle(PhoneHasher $phoneHasher): int
    {
        if (! (bool) config('royadarman.panel_demo_access')) {
            $this->error('PANEL_DEMO_ACCESS is disabled. Refusing to create demo identities or data.');

            return self::FAILURE;
        }

        if ((bool) config('royadarman.intake_enabled')) {
            $this->error('INTAKE_ENABLED must remain false while controlled panel demo access is enabled.');

            return self::FAILURE;
        }

        if (app()->environment('production') && ! (bool) $this->option('force')) {
            $this->error('Production demo seeding requires the explicit --force option.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($phoneHasher): void {
            $users = [];
            foreach (PanelDemoRegistry::identities() as $alias => $identity) {
                $users[$alias] = User::query()->updateOrCreate(
                    ['email' => $identity['email']],
                    [
                        'name' => $identity['name'],
                        'password' => null,
                        'role' => $identity['role']->value,
                        'locale' => 'fa',
                        'phone' => null,
                        'phone_hash' => null,
                        'totp_secret' => null,
                        'mfa_recovery_codes' => null,
                        'is_active' => true,
                    ],
                );
            }

            $clinic = Clinic::query()->updateOrCreate(
                ['name' => 'TEST Demo Clinic'],
                ['city' => 'Tehran', 'area_code' => 'test-demo', 'is_active' => true],
            );

            ClinicMembership::query()->updateOrCreate(
                ['clinic_id' => $clinic->id, 'user_id' => $users['clinic']->id],
                ['membership_role' => 'contact', 'active_from' => now()->subDay(), 'active_until' => null],
            );

            $licence = 'TEST-DEMO-LICENCE';
            Practitioner::query()->updateOrCreate(
                ['user_id' => $users['clinician']->id],
                [
                    'licence_number' => encrypt($licence),
                    'licence_hash' => hash('sha256', mb_strtoupper($licence)),
                    'credential_status' => 'verified',
                    'verified_at' => now()->subDay(),
                    'expires_at' => now()->addYear(),
                ],
            );

            $fakeMobile = '00000000000';
            $commonCase = [
                'patient_user_id' => $users['client']->id,
                'priority' => 'normal',
                'patient_name' => 'TEST Demo Patient',
                'patient_mobile' => $fakeMobile,
                'patient_mobile_hash' => $phoneHasher->hash($fakeMobile),
                'tehran_area' => 'central',
                'preferred_contact_time' => 'test-only',
                'contact_reason' => 'TEST synthetic demonstration record. No real patient information.',
                'budget_band' => 'test',
                'current_coordinator_id' => $users['coordinator']->id,
                'submitted_at' => now()->subDay(),
                'closed_at' => null,
                'version' => 1,
                'source_language' => 'fa',
                'currency' => 'IRR',
                'budget_input_unit' => 'toman',
            ];

            $clinicalCase = PatientCase::query()->updateOrCreate(
                ['public_reference' => 'TEST-DEMO-OPG-001'],
                [
                    ...$commonCase,
                    'service_type' => 'opg_review',
                    'status' => 'clinician_review',
                ],
            );

            $referralCase = PatientCase::query()->updateOrCreate(
                ['public_reference' => 'TEST-DEMO-REF-001'],
                [
                    ...$commonCase,
                    'service_type' => 'guidance_referral',
                    'status' => 'referred',
                ],
            );

            foreach ([$clinicalCase, $referralCase] as $case) {
                CaseAssignment::query()->updateOrCreate(
                    [
                        'case_id' => $case->id,
                        'assignee_user_id' => $users['coordinator']->id,
                        'purpose' => 'coordination',
                    ],
                    [
                        'assigned_by_user_id' => $users['admin']->id,
                        'assigned_at' => now()->subDay(),
                        'released_at' => null,
                    ],
                );
            }

            CaseAssignment::query()->updateOrCreate(
                [
                    'case_id' => $clinicalCase->id,
                    'assignee_user_id' => $users['clinician']->id,
                    'purpose' => 'clinical_review',
                ],
                [
                    'assigned_by_user_id' => $users['coordinator']->id,
                    'assigned_at' => now()->subHours(20),
                    'released_at' => null,
                ],
            );

            ReviewRevision::query()->updateOrCreate(
                ['case_id' => $clinicalCase->id, 'revision_number' => 1],
                [
                    'clinician_user_id' => $users['clinician']->id,
                    'supersedes_id' => null,
                    'source_language' => 'fa',
                    'image_adequacy' => 'TEST synthetic review: image adequacy not clinically assessed.',
                    'observations' => 'TEST synthetic demonstration content only.',
                    'limitations' => 'No real image or patient data is attached to this record.',
                    'options' => 'TEST workflow placeholder; not medical advice.',
                    'recommended_next_step' => 'TEST workflow placeholder; no clinical recommendation.',
                    'budget_band' => 'test',
                    'signed_at' => null,
                ],
            );

            $proposal = ReferralProposal::query()->updateOrCreate(
                ['case_id' => $referralCase->id, 'clinic_id' => $clinic->id],
                [
                    'proposed_by_user_id' => $users['coordinator']->id,
                    'status' => 'accepted',
                    'reasoning' => 'TEST synthetic referral used only to demonstrate role-scoped panel access.',
                    'source_language' => 'fa',
                    'proposed_at' => now()->subDay(),
                    'withdrawn_at' => null,
                    'decided_at' => now()->subHours(20),
                ],
            );

            $policyContent = 'TEST synthetic referral-sharing consent for controlled panel demonstration only.';
            $policy = PolicyVersion::query()->firstOrCreate(
                ['policy_key' => 'referral_sharing', 'version' => 'panel-demo-v1', 'locale' => 'fa'],
                [
                    'content' => $policyContent,
                    'content_hash' => hash('sha256', $policyContent),
                    'published_at' => now()->subDay(),
                ],
            );

            $consent = ConsentEvent::query()->firstOrCreate(
                [
                    'subject_user_id' => $users['client']->id,
                    'case_id' => $referralCase->id,
                    'policy_version_id' => $policy->id,
                    'purpose' => 'referral_sharing',
                    'decision' => 'accepted',
                    'revoked_at' => null,
                ],
                [
                    'locale' => 'fa',
                    'channel' => 'system',
                    'ip_hash' => hash('sha256', 'panel-demo-seed'),
                    'user_agent_hash' => hash('sha256', 'panel-demo-seed'),
                    'created_at' => now()->subHours(20),
                ],
            );

            ReferralGrant::query()->updateOrCreate(
                ['proposal_id' => $proposal->id],
                [
                    'consent_event_id' => $consent->id,
                    'case_id' => $referralCase->id,
                    'clinic_id' => $clinic->id,
                    'scope' => ['contact', 'service_need'],
                    'granted_at' => now()->subHours(20),
                    'expires_at' => now()->addDays(7),
                    'revoked_at' => null,
                ],
            );

            AuditEvent::query()->create([
                'actor_user_id' => $users['admin']->id,
                'action' => 'demo.panel.seeded',
                'resource_type' => 'panel_demo',
                'resource_id' => null,
                'result' => 'success',
                'context' => [
                    'identity_count' => count($users),
                    'case_references' => ['TEST-DEMO-OPG-001', 'TEST-DEMO-REF-001'],
                ],
                'correlation_id' => (string) Str::ulid(),
                'created_at' => now(),
            ]);
        });

        $this->info('Synthetic TEST panel demo data is ready. No passwords, OTP secrets, real patient data, diagnoses or medical images were created.');

        return self::SUCCESS;
    }
}
