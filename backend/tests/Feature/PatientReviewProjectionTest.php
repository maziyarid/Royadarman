<?php

namespace Tests\Feature;

use App\Domain\Cases\Enums\CaseStatus;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Models\ClinicalDocument;
use App\Models\ConsentEvent;
use App\Models\PatientCase;
use App\Models\PolicyVersion;
use App\Models\ReviewRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class PatientReviewProjectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_sees_only_a_signed_review_that_has_a_published_event(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $clinician = User::factory()->create(['role' => 'clinician']);
        $case = PatientCase::query()->create([
            'public_reference' => 'RD-'.strtoupper(Str::random(8)),
            'patient_user_id' => $patient->id,
            'service_type' => 'opg_review',
            'status' => CaseStatus::ClinicianReview,
            'patient_mobile' => '09120000000',
            'patient_mobile_hash' => hash('sha256', Str::random()),
            'budget_band' => 'balanced',
            'source_language' => 'fa',
            'version' => 1,
        ]);
        $document = $this->document($case);

        $signedOnly = $this->review($case, $clinician, $document, 1);
        $signedOnly->forceFill(['signed_at' => now()])->save();

        $published = $this->review($case, $clinician, $document, 2);
        $published->forceFill(['signed_at' => now()->subMinute()])->save();
        $published->publicationEvents()->create([
            'actor_user_id' => $clinician->id,
            'event' => 'published',
            'created_at' => now(),
        ]);

        $unsigned = $this->review($case, $clinician, $document, 3);

        $response = $this->actingAs($patient)->get('/en/panel/cases/'.$case->id);
        $response->assertOk();
        $response->assertSee('observation-2', false);
        $response->assertSee('opg.png', false);
        $response->assertDontSee('observation-1', false);
        $response->assertDontSee('observation-3', false);
        $response->assertViewHas('reviews', function ($reviews) use ($published, $signedOnly, $unsigned): bool {
            $ids = $reviews->pluck('id')->all();

            return $ids === [$published->id]
                && $reviews->first()->observations === 'observation-2'
                && ! in_array($signedOnly->id, $ids, true)
                && ! in_array($unsigned->id, $ids, true);
        });
    }

    public function test_assigned_clinician_sees_the_decrypted_name_only_for_current_consent(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $clinician = User::factory()->create(['role' => 'clinician']);
        $this->practitioner($clinician);
        $case = PatientCase::query()->create([
            'public_reference' => 'RD-'.strtoupper(Str::random(8)),
            'patient_user_id' => $patient->id,
            'service_type' => 'opg_review',
            'status' => CaseStatus::ClinicianReview,
            'patient_mobile' => '09120000000',
            'patient_mobile_hash' => hash('sha256', Str::random()),
            'budget_band' => 'balanced',
            'source_language' => 'fa',
            'version' => 1,
        ]);
        DB::table('case_assignments')->insert([
            'id' => (string) Str::ulid(),
            'case_id' => $case->id,
            'assignee_user_id' => $clinician->id,
            'assigned_by_user_id' => $clinician->id,
            'purpose' => 'clinical_review',
            'assigned_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $visible = $this->document($case, 'visible.png');
        $revoked = $this->document($case, 'revoked.png');
        $revoked->consentEvent()->update(['revoked_at' => now()]);
        $scanning = $this->document($case, 'scanning.png', DocumentStatus::Scanning);

        $response = $this->actingAs($clinician)->get('/en/panel/cases/'.$case->id);
        $response->assertOk();
        $response->assertSee('visible.png', false);
        $response->assertDontSee('revoked.png', false);
        $response->assertDontSee('scanning.png', false);
        $response->assertViewHas('documents', function ($documents) use ($visible): bool {
            return $documents->pluck('id')->all() === [$visible->id]
                && $documents->first()->original_name === 'visible.png'
                && $documents->first()->status === 'approved';
        });
    }

    public function test_clinician_panel_counts_only_a_review_with_a_published_event(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $clinician = User::factory()->create(['role' => 'clinician']);
        $case = PatientCase::query()->create([
            'public_reference' => 'RD-'.strtoupper(Str::random(8)),
            'patient_user_id' => $patient->id,
            'service_type' => 'opg_review',
            'status' => CaseStatus::ClinicianReview,
            'patient_mobile' => '09120000000',
            'patient_mobile_hash' => hash('sha256', Str::random()),
            'budget_band' => 'balanced',
            'source_language' => 'fa',
            'version' => 1,
        ]);
        $document = $this->document($case);
        $this->review($case, $clinician, $document, 1);

        $signedOnly = $this->review($case, $clinician, $document, 2);
        $signedOnly->forceFill(['signed_at' => now()])->save();

        $published = $this->review($case, $clinician, $document, 3);
        $published->forceFill(['signed_at' => now()])->save();
        $published->publicationEvents()->create([
            'actor_user_id' => $clinician->id,
            'event' => 'published',
            'created_at' => now(),
        ]);

        $this->actingAs($clinician)
            ->get('/en/panel')
            ->assertOk()
            ->assertViewHas('metrics', function (array $metrics): bool {
                return $metrics['published_reviews'] === 1
                    && $metrics['draft_reviews'] === 1;
            });
    }

    public function test_clinician_panel_does_not_count_a_superseded_published_review(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $clinician = User::factory()->create(['role' => 'clinician']);
        $case = PatientCase::query()->create([
            'public_reference' => 'RD-'.strtoupper(Str::random(8)),
            'patient_user_id' => $patient->id,
            'service_type' => 'opg_review',
            'status' => CaseStatus::ClinicianReview,
            'patient_mobile' => '09120000000',
            'patient_mobile_hash' => hash('sha256', Str::random()),
            'budget_band' => 'balanced',
            'source_language' => 'fa',
            'version' => 1,
        ]);
        $document = $this->document($case);
        $this->review($case, $clinician, $document, 1);

        $earlier = $this->review($case, $clinician, $document, 2);
        $earlier->forceFill(['signed_at' => now()->subMinute()])->save();
        $earlier->publicationEvents()->create([
            'actor_user_id' => $clinician->id,
            'event' => 'published',
            'created_at' => now()->subMinute(),
        ]);

        $current = $this->review($case, $clinician, $document, 3);
        $current->forceFill([
            'signed_at' => now(),
            'supersedes_id' => $earlier->id,
        ])->save();
        $current->publicationEvents()->create([
            'actor_user_id' => $clinician->id,
            'event' => 'published',
            'created_at' => now(),
        ]);

        $this->actingAs($clinician)
            ->get('/en/panel')
            ->assertOk()
            ->assertViewHas('metrics', function (array $metrics): bool {
                return $metrics['published_reviews'] === 1
                    && $metrics['draft_reviews'] === 1;
            });
    }

    private function practitioner(User $clinician): void
    {
        DB::table('practitioners')->insert([
            'id' => (string) Str::ulid(),
            'user_id' => $clinician->id,
            'licence_number' => encrypt('LIC-'.$clinician->id),
            'licence_hash' => hash('sha256', 'LIC-'.$clinician->id),
            'credential_status' => 'verified',
            'verified_at' => now()->subDay(),
            'expires_at' => now()->addYear(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function document(PatientCase $case, string $name = 'opg.png', DocumentStatus $status = DocumentStatus::Approved): ClinicalDocument
    {
        $version = 'projection-'.Str::lower((string) Str::ulid());
        $policy = PolicyVersion::query()->create([
            'policy_key' => 'opg_document_sharing',
            'version' => $version,
            'locale' => 'fa',
            'content' => 'sharing '.$version,
            'content_hash' => hash('sha256', 'sharing '.$version),
            'published_at' => now(),
        ]);
        $consent = ConsentEvent::query()->create([
            'subject_user_id' => $case->patient_user_id,
            'case_id' => $case->id,
            'policy_version_id' => $policy->id,
            'purpose' => 'opg_document_sharing',
            'decision' => 'accepted',
            'locale' => 'fa',
            'channel' => 'web',
            'ip_hash' => hash('sha256', 'ip'),
            'user_agent_hash' => hash('sha256', 'ua'),
            'created_at' => now(),
        ]);

        return ClinicalDocument::query()->create([
            'case_id' => $case->id,
            'uploaded_by_user_id' => $case->patient_user_id,
            'consent_event_id' => $consent->id,
            'original_name' => $name,
            'storage_disk' => 'private-opg',
            'storage_key' => 'cases/'.$case->id.'/'.Str::ulid().'.png',
            'detected_mime' => 'image/png',
            'byte_size' => 68,
            'sha256' => hash('sha256', $name),
            'status' => $status,
        ]);
    }

    private function review(PatientCase $case, User $clinician, ClinicalDocument $document, int $number): ReviewRevision
    {
        return ReviewRevision::query()->create([
            'case_id' => $case->id,
            'clinician_user_id' => $clinician->id,
            'clinical_document_id' => $document->id,
            'revision_number' => $number,
            'source_language' => 'fa',
            'image_adequacy' => 'adequate',
            'observations' => 'observation-'.$number,
            'limitations' => 'limits',
            'options' => 'options',
            'recommended_next_step' => 'next',
        ]);
    }
}
