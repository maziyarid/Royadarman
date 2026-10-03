<?php

namespace Tests\Feature;

use App\Models\ClinicalDocument;
use App\Models\ConsentEvent;
use App\Models\PatientCase;
use App\Models\PolicyVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class DeletedDocumentAccessTest extends TestCase
{
    use RefreshDatabase;

    private const BYTES = 'PRIVATE-SYNTHETIC-DOCUMENT-BYTES';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private-opg');
    }

    public static function viewers(): array
    {
        return ['patient' => ['patient'], 'assigned clinician' => ['clinician']];
    }

    #[DataProvider('viewers')]
    public function test_deleted_approved_document_status_is_not_readable(string $role): void
    {
        [$patient, $clinician, $case, $document] = $this->fixture();
        $document->update(['deleted_at' => now()]);
        $before = $this->snapshot();

        $this->actingAs($role === 'patient' ? $patient : $clinician)->getJson($this->url($case, $document))
            ->assertNotFound()->assertJsonPath('error.code', 'error.not_found')
            ->assertJsonMissing(['id' => $document->id]);
        $this->assertSame($before, $this->snapshot());
        $this->assertDatabaseCount('document_access_events', 0);
        $this->assertSame(self::BYTES, Storage::disk('private-opg')->get($document->storage_key));
    }

    #[DataProvider('viewers')]
    public function test_deleted_approved_document_bytes_are_not_streamed_or_logged_as_success(string $role): void
    {
        [$patient, $clinician, $case, $document] = $this->fixture();
        $document->update(['deleted_at' => now()]);
        $before = $this->snapshot();

        $response = $this->actingAs($role === 'patient' ? $patient : $clinician)->getJson($this->url($case, $document).'/content');
        if ($response->getStatusCode() === 200) {
            // Characterise an unexpected success with actual stored bytes, not headers alone.
            $this->assertSame(self::BYTES, $response->streamedContent());
            $this->assertDatabaseCount('document_access_events', 1);
        }
        $response->assertNotFound()->assertJsonPath('error.code', 'error.not_found')->assertDontSee(self::BYTES);
        $response->assertHeader('Content-Type', 'application/json');
        $this->assertSame($before, $this->snapshot());
        $this->assertDatabaseCount('document_access_events', 0);
        $this->assertSame(self::BYTES, Storage::disk('private-opg')->get($document->storage_key));
    }

    #[DataProvider('viewers')]
    public function test_clean_approved_source_still_exposes_status_and_exact_bytes_with_existing_audit(string $role): void
    {
        [$patient, $clinician, $case, $document] = $this->fixture();
        $viewer = $role === 'patient' ? $patient : $clinician;
        $this->actingAs($viewer)->getJson($this->url($case, $document))->assertOk()
            ->assertJsonPath('data.id', $document->id)->assertJsonPath('data.status', 'approved')
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertDatabaseCount('document_access_events', 0);
        $response = $this->actingAs($viewer)->getJson($this->url($case, $document).'/content')->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Security-Policy', "default-src 'none'; sandbox");
        $this->assertSame(self::BYTES, $response->streamedContent());
        $this->assertDatabaseCount('document_access_events', 1);
        $this->assertDatabaseHas('document_access_events', [
            'document_id' => $document->id, 'actor_user_id' => $viewer->id, 'case_id' => $case->id,
            'action' => 'stream', 'result' => 'success',
        ]);
    }

    public function test_rejected_and_failed_scan_states_retain_existing_participant_errors_without_stream_audits(): void
    {
        [$patient, $clinician, $case, $document] = $this->fixture();
        foreach (['rejected' => [403, 'document.rejected'], 'scan_failed' => [503, 'document.scan_failed']] as $state => [$status, $code]) {
            $document->update(['status' => $state]);
            foreach ([$patient, $clinician] as $viewer) {
                $this->actingAs($viewer)->getJson($this->url($case, $document).'/content')
                    ->assertStatus($status)->assertJsonPath('error.code', $code)->assertDontSee(self::BYTES);
                $this->actingAs($viewer)->getJson($this->url($case, $document))->assertNotFound();
            }
        }
        $this->assertDatabaseCount('document_access_events', 0);
    }

    public function test_case_role_and_ownership_denials_still_hide_both_endpoints(): void
    {
        [$patient, $clinician, $case, $document] = $this->fixture();
        [$otherPatient, $otherClinician, $otherCase] = $this->fixture();
        $before = $this->snapshot();
        foreach (['patient', 'owner', 'tech_admin', 'coordinator', 'clinic_rep', 'clinician'] as $role) {
            $stranger = User::factory()->create(['role' => $role]);
            foreach (['', '/content'] as $suffix) {
                $this->actingAs($stranger)->getJson($this->url($case, $document).$suffix)->assertNotFound()->assertDontSee(self::BYTES);
            }
        }
        foreach ([$patient, $clinician] as $viewer) {
            foreach (['', '/content'] as $suffix) {
                $this->actingAs($viewer)->getJson($this->url($otherCase, $document).$suffix)->assertNotFound()->assertDontSee(self::BYTES);
            }
        }
        $this->assertSame($before, $this->snapshot());
    }

    public function test_clinician_consent_assignment_and_credential_revocation_still_block_access(): void
    {
        foreach (['revoked_consent', 'released_assignment', 'expired_credential'] as $state) {
            [$patient, $clinician, $case, $document] = $this->fixture();
            match ($state) {
                'revoked_consent' => $document->consentEvent()->update(['revoked_at' => now()]),
                'released_assignment' => DB::table('case_assignments')->where('case_id', $case->id)->update(['released_at' => now()]),
                'expired_credential' => DB::table('practitioners')->where('user_id', $clinician->id)->update(['expires_at' => now()]),
            };
            $before = $this->snapshot();
            foreach (['', '/content'] as $suffix) {
                $this->actingAs($clinician)->getJson($this->url($case, $document).$suffix)->assertNotFound()->assertDontSee(self::BYTES);
            }
            $this->assertSame($before, $this->snapshot());
        }
    }

    private function fixture(): array
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $clinician = User::factory()->create(['role' => 'clinician']);
        DB::table('practitioners')->insert([
            'id' => (string) Str::ulid(), 'user_id' => $clinician->id,
            'licence_number' => encrypt('SYNTHETIC-LICENCE'), 'licence_hash' => hash('sha256', Str::ulid()),
            'credential_status' => 'verified', 'verified_at' => now()->subDay(), 'expires_at' => now()->addYear(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $case = PatientCase::query()->create([
            'public_reference' => 'RD-'.Str::random(8), 'patient_user_id' => $patient->id,
            'patient_mobile' => '09120000001', 'patient_mobile_hash' => hash('sha256', Str::ulid()),
            'service_type' => 'opg_review', 'status' => 'clinician_review', 'source_language' => 'fa', 'budget_band' => 'balanced',
        ]);
        DB::table('case_assignments')->insert([
            'id' => (string) Str::ulid(), 'case_id' => $case->id, 'assignee_user_id' => $clinician->id,
            'assigned_by_user_id' => $clinician->id, 'purpose' => 'clinical_review', 'assigned_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $policy = PolicyVersion::query()->create([
            'policy_key' => 'opg_document_sharing', 'version' => (string) Str::ulid(), 'locale' => 'fa',
            'content' => 'Synthetic consent', 'content_hash' => hash('sha256', 'Synthetic consent'), 'published_at' => now(),
        ]);
        $consent = ConsentEvent::query()->create([
            'subject_user_id' => $patient->id, 'case_id' => $case->id, 'policy_version_id' => $policy->id,
            'purpose' => 'opg_document_sharing', 'decision' => 'accepted', 'locale' => 'fa', 'channel' => 'web',
            'ip_hash' => hash('sha256', 'synthetic'), 'user_agent_hash' => hash('sha256', 'synthetic'), 'created_at' => now(),
        ]);
        $document = ClinicalDocument::query()->create([
            'case_id' => $case->id, 'uploaded_by_user_id' => $patient->id, 'consent_event_id' => $consent->id,
            'original_name' => 'synthetic-source.png', 'storage_disk' => 'private-opg', 'storage_key' => 'synthetic-private/'.Str::ulid(),
            'detected_mime' => 'image/png', 'byte_size' => strlen(self::BYTES), 'sha256' => hash('sha256', self::BYTES), 'status' => 'approved',
        ]);
        Storage::disk('private-opg')->put($document->storage_key, self::BYTES);

        return [$patient, $clinician, $case, $document];
    }

    private function url(PatientCase $case, ClinicalDocument $document): string
    {
        return '/api/v1/cases/'.$case->id.'/documents/'.$document->id;
    }

    private function snapshot(): array
    {
        return collect(['clinical_documents', 'document_access_events', 'review_revisions', 'publication_events', 'audit_events', 'outbox_events'])
            ->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()])->all();
    }
}
