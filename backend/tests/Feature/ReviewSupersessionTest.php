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

final class ReviewSupersessionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('royadarman.intake_enabled', true);
    }

    public function test_publish_links_only_the_same_clinicians_earlier_published_revision(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $first = User::factory()->create(['role' => 'clinician']);
        $second = User::factory()->create(['role' => 'clinician']);
        $this->practitioner($first);
        $this->practitioner($second);
        $case = $this->makeCase($patient);
        $this->assign($first, $case);
        $this->assign($second, $case);
        $document = $this->document($case);

        $firstEarlier = $this->publish($first, $case, $document, 'earlier-from-first');
        $secondRevision = $this->publish($second, $case, $document, 'from-second');
        $firstLater = $this->publish($first, $case, $document, 'later-from-first');

        $this->assertNull($firstEarlier->fresh()->supersedes_id);
        $this->assertNull($secondRevision->fresh()->supersedes_id);
        $this->assertSame($firstEarlier->id, $firstLater->fresh()->supersedes_id);

        $this->actingAs($patient)
            ->get('/en/panel/cases/'.$case->id)
            ->assertOk()
            ->assertSee('earlier-from-first', false)
            ->assertSee('from-second', false)
            ->assertSee('later-from-first', false);

        $this->actingAs($first)
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonFragment(['id' => $firstLater->id])
            ->assertJsonMissing(['id' => $firstEarlier->id]);

        $this->actingAs($second)
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonFragment(['id' => $secondRevision->id]);
    }

    private function publish(User $clinician, PatientCase $case, ClinicalDocument $document, string $observation): ReviewRevision
    {
        $created = $this->actingAs($clinician)
            ->postJson("/api/v1/staff/cases/{$case->id}/reviews", [
                'source_language' => 'fa',
                'clinical_document_id' => $document->id,
                'image_adequacy' => 'adequate',
                'observations' => $observation,
                'limitations' => 'limits',
                'options' => 'options',
                'recommended_next_step' => 'next',
            ])
            ->assertCreated();

        $revision = ReviewRevision::query()->findOrFail($created->json('data.id'));
        $this->actingAs($clinician)
            ->postJson("/api/v1/staff/cases/{$case->id}/reviews/{$revision->id}/publish")
            ->assertOk();

        return $revision;
    }

    private function makeCase(User $patient): PatientCase
    {
        return PatientCase::query()->create([
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

    private function assign(User $clinician, PatientCase $case): void
    {
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
    }

    private function document(PatientCase $case): ClinicalDocument
    {
        $version = 'supersede-'.Str::lower((string) Str::ulid());
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
            'original_name' => 'opg.png',
            'storage_disk' => 'private-opg',
            'storage_key' => 'cases/'.$case->id.'/'.Str::ulid().'.png',
            'detected_mime' => 'image/png',
            'byte_size' => 68,
            'sha256' => hash('sha256', 'image-bytes'),
            'status' => DocumentStatus::Approved,
        ]);
    }
}
