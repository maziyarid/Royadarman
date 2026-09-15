<?php

namespace App\Console\Commands;

use App\Domain\Documents\Enums\DocumentStatus;
use App\Models\AuditEvent;
use App\Models\CaseAssignment;
use App\Models\Clinic;
use App\Models\ClinicalDocument;
use App\Models\ClinicMembership;
use App\Models\ConsentEvent;
use App\Models\HomeServiceRequest;
use App\Models\PatientCase;
use App\Models\PolicyVersion;
use App\Models\Practitioner;
use App\Models\ReferralGrant;
use App\Models\ReferralProposal;
use App\Models\ReviewRevision;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\User;
use App\Support\PanelDemoRegistry;
use App\Support\PhoneHasher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

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

        try {
            DB::transaction(function () use ($phoneHasher): void {
                $users = [];
                foreach (PanelDemoRegistry::identities() as $alias => $identity) {
                    $existing = User::query()
                        ->where('email', $identity['email'])
                        ->lockForUpdate()
                        ->first();

                    if ($existing !== null) {
                        if (! $this->isSafeExistingDemoIdentity($existing, $identity['role']->value)) {
                            throw new RuntimeException(
                                'Reserved demo identity collision for '.$identity['email'].'. Existing credentials or role were not modified.',
                            );
                        }

                        $existing->forceFill([
                            'name' => $identity['name'],
                            'locale' => 'fa',
                            'is_active' => true,
                        ])->save();
                        $users[$alias] = $existing;

                        continue;
                    }

                    $users[$alias] = User::query()->create([
                        'email' => $identity['email'],
                        'name' => $identity['name'],
                        'password' => null,
                        'role' => $identity['role']->value,
                        'locale' => 'fa',
                        'phone' => null,
                        'phone_hash' => null,
                        'totp_secret' => null,
                        'mfa_recovery_codes' => null,
                        'is_active' => true,
                    ]);
                }

                $clinic = $this->resolveSyntheticDemoClinic();

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
                    ['public_reference' => PanelDemoRegistry::OPG_CASE_REFERENCE],
                    [
                        ...$commonCase,
                        'service_type' => 'opg_review',
                        'status' => 'clinician_review',
                    ],
                );

                $referralCase = PatientCase::query()->updateOrCreate(
                    ['public_reference' => PanelDemoRegistry::REFERRAL_CASE_REFERENCE],
                    [
                        ...$commonCase,
                        'service_type' => 'guidance_referral',
                        'status' => 'referred',
                    ],
                );

                $homeCase = PatientCase::query()->updateOrCreate(
                    ['public_reference' => PanelDemoRegistry::HOME_CASE_REFERENCE],
                    [
                        ...$commonCase,
                        'service_type' => 'home_dentistry',
                        'status' => 'home_visit_proposed',
                    ],
                );

                foreach ([$clinicalCase, $referralCase, $homeCase] as $case) {
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

                $document = ClinicalDocument::query()->updateOrCreate(
                    ['storage_key' => PanelDemoRegistry::DOCUMENT_STORAGE_KEY],
                    [
                        'case_id' => $clinicalCase->id,
                        'uploaded_by_user_id' => $users['client']->id,
                        'original_name' => 'TEST-DEMO-NO-IMAGE.txt',
                        'storage_disk' => 'local',
                        'detected_mime' => 'text/plain',
                        'byte_size' => 0,
                        'sha256' => hash('sha256', 'royadarman-panel-demo-no-image'),
                        'status' => DocumentStatus::Rejected,
                        'scan_provider' => 'panel-demo',
                        'scan_error_code' => 'demo.synthetic_no_image',
                        'approved_at' => null,
                        'deleted_at' => null,
                    ],
                );

                ReviewRevision::query()->updateOrCreate(
                    ['case_id' => $clinicalCase->id, 'revision_number' => 1],
                    [
                        'clinician_user_id' => $users['clinician']->id,
                        'clinical_document_id' => $document->id,
                        'supersedes_id' => null,
                        'source_language' => 'fa',
                        'image_adequacy' => 'TEST synthetic review: image adequacy not clinically assessed.',
                        'observations' => 'TEST synthetic demonstration content only.',
                        'limitations' => 'No real image or patient data is attached to this record.',
                        'options' => 'TEST synthetic demonstration: no treatment options are offered because no real image exists.',
                        'recommended_next_step' => 'TEST synthetic demonstration: this record is not a clinical recommendation.',
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

                HomeServiceRequest::query()->updateOrCreate(
                    ['case_id' => $homeCase->id],
                    [
                        'patient_user_id' => $users['client']->id,
                        'tehran_area' => 'central',
                        'status' => 'coordinator_review',
                        'version' => 1,
                    ],
                );

                $conversation = SupportConversation::query()->updateOrCreate(
                    [
                        'patient_user_id' => $users['client']->id,
                        'subject' => PanelDemoRegistry::SUPPORT_SUBJECT,
                    ],
                    [
                        'case_id' => $referralCase->id,
                        'category' => 'coordination',
                        'status' => 'open',
                        'priority' => 'normal',
                        'assignee_user_id' => $users['coordinator']->id,
                        'opened_at' => now()->subHours(6),
                        'source_language' => 'fa',
                    ],
                );

                SupportMessage::query()->updateOrCreate(
                    [
                        'conversation_id' => $conversation->id,
                        'author_user_id' => $users['client']->id,
                    ],
                    [
                        'is_internal' => false,
                        'body' => 'TEST synthetic patient message. No real medical history.',
                        'source_language' => 'fa',
                        'created_at' => now()->subHours(6),
                    ],
                );

                SupportMessage::query()->updateOrCreate(
                    [
                        'conversation_id' => $conversation->id,
                        'author_user_id' => $users['coordinator']->id,
                    ],
                    [
                        'is_internal' => false,
                        'body' => 'TEST synthetic coordinator reply. The desk has the request; this is not clinical advice.',
                        'source_language' => 'fa',
                        'created_at' => now()->subHours(5),
                    ],
                );

                AuditEvent::query()->firstOrCreate(
                    [
                        'action' => PanelDemoRegistry::AUDIT_SEED_ACTION,
                        'resource_type' => PanelDemoRegistry::AUDIT_RESOURCE_TYPE,
                        'resource_id' => PanelDemoRegistry::CLINIC_DEMO_KEY,
                    ],
                    [
                        'actor_user_id' => $users['admin']->id,
                        'result' => 'success',
                        'context' => [
                            'identity_count' => count($users),
                            'case_references' => PanelDemoRegistry::caseReferences(),
                            'clinic_demo_key' => PanelDemoRegistry::CLINIC_DEMO_KEY,
                        ],
                        'correlation_id' => (string) Str::ulid(),
                        'created_at' => now(),
                    ],
                );
            });
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Synthetic TEST panel demo data is ready. No passwords, OTP secrets, real patient data, diagnoses or medical images were created.');

        return self::SUCCESS;
    }

    private function resolveSyntheticDemoClinic(): Clinic
    {
        $clinic = Clinic::query()
            ->where('synthetic_demo_key', PanelDemoRegistry::CLINIC_DEMO_KEY)
            ->lockForUpdate()
            ->first();

        if ($clinic !== null) {
            $clinic->forceFill([
                'name' => PanelDemoRegistry::CLINIC_DISPLAY_NAME,
                'city' => 'Tehran',
                'area_code' => PanelDemoRegistry::CLINIC_AREA_CODE,
                'is_active' => true,
            ])->save();

            return $clinic;
        }

        $nameCollision = Clinic::query()
            ->where('name', PanelDemoRegistry::CLINIC_DISPLAY_NAME)
            ->where(function ($query): void {
                $query->whereNull('synthetic_demo_key')
                    ->orWhere('synthetic_demo_key', '!=', PanelDemoRegistry::CLINIC_DEMO_KEY);
            })
            ->lockForUpdate()
            ->first();

        if ($nameCollision !== null) {
            throw new RuntimeException(
                'Reserved demo clinic name collision. Existing clinic "'.PanelDemoRegistry::CLINIC_DISPLAY_NAME.'" is not the synthetic demo clinic and was not modified.',
            );
        }

        return Clinic::query()->create([
            'name' => PanelDemoRegistry::CLINIC_DISPLAY_NAME,
            'city' => 'Tehran',
            'area_code' => PanelDemoRegistry::CLINIC_AREA_CODE,
            'synthetic_demo_key' => PanelDemoRegistry::CLINIC_DEMO_KEY,
            'is_active' => true,
        ]);
    }

    private function isSafeExistingDemoIdentity(User $user, string $expectedRole): bool
    {
        return $user->getRawOriginal('role') === $expectedRole
            && $user->getRawOriginal('password') === null
            && $user->getRawOriginal('phone') === null
            && $user->getRawOriginal('phone_hash') === null
            && $user->getRawOriginal('totp_secret') === null
            && $user->getRawOriginal('mfa_recovery_codes') === null;
    }
}
