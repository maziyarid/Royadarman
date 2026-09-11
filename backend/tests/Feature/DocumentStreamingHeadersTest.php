<?php

namespace Tests\Feature;

use App\Domain\Documents\Enums\DocumentStatus;
use App\Models\ClinicalDocument;
use App\Models\ConsentEvent;
use App\Models\PatientCase;
use App\Models\PolicyVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class DocumentStreamingHeadersTest extends TestCase
{
    use RefreshDatabase;

    private const PNG_BYTES = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('royadarman.intake_enabled', true);
        Storage::fake('private-opg');
    }

    public function test_owner_patient_streams_approved_document_with_security_headers(): void
    {
        [$patient, $case, $document] = $this->seedApprovedDocument();
        $response = $this->actingAs($patient)
            ->getJson("/api/v1/cases/{$case->id}/documents/{$document->id}/content");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/png');
        $response->assertHeader('Cache-Control', 'no-store, private');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('inline', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringNotContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
        $this->assertSame((string) $document->byte_size, (string) $response->headers->get('Content-Length'));
    }

    public function test_approved_clinician_streams_approved_document(): void
    {
        [$patient, $case, $document] = $this->seedApprovedDocument();
        $clinician = User::factory()->create(['role' => 'clinician']);
        $this->makeAssignedVerifiedClinician($clinician, $case);

        $this->actingAs($clinician)
            ->getJson("/api/v1/cases/{$case->id}/documents/{$document->id}/content")
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_rejected_document_cannot_stream(): void
    {
        [$patient, $case, $document] = $this->seedApprovedDocument();
        $document->update(['status' => DocumentStatus::Rejected]);

        $this->actingAs($patient)
            ->getJson("/api/v1/cases/{$case->id}/documents/{$document->id}/content")
            ->assertForbidden()
            ->assertJsonPath('error.code', 'document.rejected');
    }

    public function test_scan_failed_document_cannot_stream(): void
    {
        [$patient, $case, $document] = $this->seedApprovedDocument();
        $document->update(['status' => DocumentStatus::ScanFailed]);

        $this->actingAs($patient)
            ->getJson("/api/v1/cases/{$case->id}/documents/{$document->id}/content")
            ->assertStatus(503)
            ->assertJsonPath('error.code', 'document.scan_failed');
    }

    public function test_another_patient_cannot_stream_someone_elses_document(): void
    {
        [$patient, $case, $document] = $this->seedApprovedDocument();
        $intruder = User::factory()->create(['role' => 'patient']);

        $this->actingAs($intruder)
            ->getJson("/api/v1/cases/{$case->id}/documents/{$document->id}/content")
            ->assertNotFound();
    }

    public function test_nested_case_document_id_mismatch_is_not_found(): void
    {
        [$patientA, $caseA, $documentA] = $this->seedApprovedDocument();
        [$patientB, $caseB] = $this->seedApprovedDocument();

        $this->actingAs($patientA)
            ->getJson("/api/v1/cases/{$caseB->id}/documents/{$documentA->id}/content")
            ->assertNotFound();
    }

    public function test_csp_header_restricts_inline_content(): void
    {
        [$patient, $case, $document] = $this->seedApprovedDocument();
        $response = $this->actingAs($patient)
            ->getJson("/api/v1/cases/{$case->id}/documents/{$document->id}/content")
            ->assertOk();
        $csp = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'none'", $csp);
        $this->assertStringContainsString('sandbox', $csp);
    }

    public function test_streaming_records_an_access_audit_event(): void
    {
        [$patient, $case, $document] = $this->seedApprovedDocument();
        $this->actingAs($patient)
            ->getJson("/api/v1/cases/{$case->id}/documents/{$document->id}/content")
            ->assertOk();
        $this->assertDatabaseHas('document_access_events', [
            'document_id' => $document->id,
            'actor_user_id' => $patient->id,
            'case_id' => $case->id,
            'action' => 'stream',
            'result' => 'success',
        ]);
    }

    public function test_unauthenticated_request_is_not_streamed(): void
    {
        [$patient, $case, $document] = $this->seedApprovedDocument();
        $this->getJson("/api/v1/cases/{$case->id}/documents/{$document->id}/content")
            ->assertUnauthorized();
    }

    private function seedApprovedDocument(): array
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $case = PatientCase::query()->create([
            'public_reference' => 'RD-'.strtoupper(Str::random(8)),
            'patient_user_id' => $patient->id,
            'service_type' => 'opg_review',
            'status' => 'submitted',
            'patient_mobile' => '09121234567',
            'patient_mobile_hash' => hash('sha256', Str::random()),
            'budget_band' => 'balanced',
            'version' => 1,
        ]);
        $policyVersion = 'approved-'.Str::random(6);
        $policy = PolicyVersion::query()->create([
            'policy_key' => 'opg_document_sharing', 'version' => $policyVersion, 'locale' => 'fa',
            'content' => 'opg text '.$policyVersion, 'content_hash' => hash('sha256', 'opg text '.$policyVersion), 'published_at' => now(),
        ]);
        $consent = ConsentEvent::query()->create([
            'subject_user_id' => $patient->id, 'case_id' => $case->id, 'policy_version_id' => $policy->id,
            'purpose' => 'opg_document_sharing', 'decision' => 'accepted', 'locale' => 'fa', 'channel' => 'web',
            'ip_hash' => hash('sha256', 'ip'), 'user_agent_hash' => hash('sha256', 'ua'), 'created_at' => now(),
        ]);
        $bytes = base64_decode(self::PNG_BYTES, true);
        $storageKey = 'cases/'.$case->id.'/opg.png';
        $document = ClinicalDocument::query()->create([
            'case_id' => $case->id,
            'uploaded_by_user_id' => $patient->id,
            'consent_event_id' => $consent->id,
            'storage_disk' => 'private-opg',
            'storage_key' => $storageKey,
            'original_name' => 'opg.png',
            'detected_mime' => 'image/png',
            'byte_size' => strlen($bytes),
            'sha256' => hash('sha256', $bytes),
            'status' => DocumentStatus::Approved,
        ]);
        Storage::disk('private-opg')->put($document->storage_key, $bytes);

        return [$patient, $case, $document];
    }

    private function makeAssignedVerifiedClinician(User $clinician, PatientCase $case): void
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
}
