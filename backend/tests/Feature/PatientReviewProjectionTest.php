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

    private function document(PatientCase $case): ClinicalDocument
    {
        $policy = PolicyVersion::query()->create([
            'policy_key' => 'opg_document_sharing',
            'version' => 'projection-1',
            'locale' => 'fa',
            'content' => 'sharing',
            'content_hash' => hash('sha256', 'sharing'),
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
            'original_name' => 'opg.png',
            'storage_disk' => 'private-opg',
            'storage_key' => 'cases/'.$case->id.'/'.Str::ulid().'.png',
            'detected_mime' => 'image/png',
            'byte_size' => 68,
            'sha256' => hash('sha256', 'image-bytes'),
            'status' => DocumentStatus::Approved,
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
