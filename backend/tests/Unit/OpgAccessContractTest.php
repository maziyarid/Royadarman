<?php

namespace Tests\Unit;

use App\Domain\Documents\OpgAccessContract;
use PHPUnit\Framework\TestCase;

final class OpgAccessContractTest extends TestCase
{
    public function test_schema_keeps_private_document_facts_and_has_no_tooth_taxonomy(): void
    {
        $blob = $this->migrations();
        $documents = $this->createBlock($blob, 'clinical_documents');

        foreach (['storage_disk', 'storage_key', 'sha256', 'status', 'retention_until', 'approved_at'] as $column) {
            $this->assertStringContainsString($column, $documents);
        }

        $this->assertStringNotContainsString('clinic_id', $documents);
        $this->assertStringContainsString('scan_attempted_at', $blob);
        $required = (string) file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_09_11_000200_require_review_document_link.php');
        $this->assertStringContainsString('clinical_document_id', $required);
        $this->assertStringContainsString('nullable(false)->change()', $required);

        $reviews = $this->createBlock($blob, 'review_revisions');
        foreach (['observations', 'signed_at', 'clinician_user_id', 'revision_number'] as $column) {
            $this->assertStringContainsString($column, $reviews);
        }

        $events = $this->createBlock($blob, 'publication_events');
        $this->assertStringContainsString("'event'", $events);
        $attempts = $this->createBlock($blob, 'scan_attempts');
        $this->assertStringContainsString('file_hash', $attempts);

        foreach (['teeth', 'tooth_findings', 'treatment_stages'] as $absent) {
            $this->assertDoesNotMatchRegularExpression("/Schema::create\(\s*'".$absent."'/", $blob);
        }

        $this->assertNull(OpgAccessContract::toothTaxonomy());
        $this->assertNull(OpgAccessContract::acceptedRetentionDays());
        $this->assertSame('proposed_not_wired', OpgAccessContract::STATUS);
    }

    public function test_opg_disks_are_not_public_and_the_scanner_defaults_off(): void
    {
        $disks = (string) file_get_contents(dirname(__DIR__, 2).'/config/filesystems.php');
        $quarantine = $this->diskBlock($disks, 'opg-quarantine');
        $private = $this->diskBlock($disks, 'private-opg');
        $public = $this->diskBlock($disks, 'public');

        $this->assertStringContainsString("'serve' => false", $quarantine);
        $this->assertStringContainsString("'serve' => false", $private);
        $this->assertStringNotContainsString("'visibility' => 'public'", $quarantine);
        $this->assertStringNotContainsString("'visibility' => 'public'", $private);
        $this->assertStringContainsString("'visibility' => 'public'", $public);

        $config = (string) file_get_contents(dirname(__DIR__, 2).'/config/royadarman.php');
        $this->assertStringContainsString("'enabled' => (bool) env('ROYADARMAN_OPG_SCANNER_ENABLED', false)", $config);

        $model = (string) file_get_contents(dirname(__DIR__, 2).'/app/Models/ClinicalDocument.php');
        $this->assertStringContainsString("'storage_key'", $model);
        $this->assertStringContainsString("['storage_key', 'scan_reference', 'scan_result']", $model);
    }

    public function test_no_title_or_scan_verdict_grants_a_read_or_a_diagnosis(): void
    {
        foreach (['owner', 'tech_admin', 'superadmin', 'dentist', 'clinician', 'coordinator', 'patient', ''] as $title) {
            $decision = OpgAccessContract::titleGrantsDocumentRead($title);
            $this->assertFalse($decision->allowed);
            $this->assertSame('title_does_not_grant_document_read', $decision->reason);
        }

        foreach (OpgAccessContract::malwareVerdicts() as $verdict) {
            $this->assertFalse(OpgAccessContract::scanIsDiagnosis($verdict));
            $decision = OpgAccessContract::interpretScan($verdict);
            $this->assertFalse($decision->allowed);
            $this->assertSame('malware_verdict_not_a_diagnosis', $decision->reason);
        }

        $unknown = OpgAccessContract::interpretScan('caries');
        $this->assertFalse($unknown->allowed);
        $this->assertSame('unknown_scan_verdict', $unknown->reason);
        $this->assertFalse(OpgAccessContract::scanIsDiagnosis('caries'));
    }

    public function test_bytes_and_review_release_stay_closed(): void
    {
        $approved = OpgAccessContract::bytesOnDisk('approved', 'private-opg');
        $this->assertFalse($approved->allowed);
        $this->assertSame('bytes_not_granted_by_this_contract', $approved->reason);

        $this->assertSame('bytes_not_approved', OpgAccessContract::bytesOnDisk('quarantined', 'opg-quarantine')->reason);
        $this->assertSame('bytes_not_approved', OpgAccessContract::bytesOnDisk('approved', 'opg-quarantine')->reason);
        $this->assertSame('bytes_not_approved', OpgAccessContract::bytesOnDisk('rejected', 'private-opg')->reason);
        $this->assertSame('public_disk_not_for_opg', OpgAccessContract::bytesOnDisk('approved', 'public')->reason);
        $this->assertSame('public_disk_not_for_opg', OpgAccessContract::bytesOnDisk('approved', 'public-cms')->reason);
        $this->assertSame('unknown_document_disk', OpgAccessContract::bytesOnDisk('approved', 's3')->reason);

        $this->assertSame('review_unsigned', OpgAccessContract::releasedText(false, false)->reason);
        $this->assertSame('publication_event_missing', OpgAccessContract::releasedText(true, false)->reason);

        $released = OpgAccessContract::releasedText(true, true);
        $this->assertFalse($released->allowed);
        $this->assertSame('released_text_not_a_byte_grant', $released->reason);

        $panel = (string) file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/PanelCaseController.php');
        $this->assertStringContainsString("whereNotNull('signed_at')", $panel);
        $this->assertStringContainsString("where('event', 'published')", $panel);
        $this->assertStringContainsString('ReviewRevision::query()', $panel);
        $this->assertStringContainsString('ClinicalDocument::query()', $panel);
        $this->assertStringContainsString("where('status', 'approved')", $panel);
        $this->assertStringContainsString("whereNull('revoked_at')", $panel);
        $this->assertStringNotContainsString("DB::table('clinical_documents')", $panel);
    }

    private function migrations(): string
    {
        $dir = dirname(__DIR__, 2).'/database/migrations';
        $this->assertDirectoryExists($dir);
        $blob = '';
        foreach (scandir($dir) ?: [] as $name) {
            if (str_ends_with($name, '.php')) {
                $blob .= (string) file_get_contents($dir.'/'.$name)."\n";
            }
        }

        return $blob;
    }

    private function createBlock(string $blob, string $table): string
    {
        $needle = "Schema::create('".$table."'";
        $start = strpos($blob, $needle);
        $this->assertNotFalse($start, $needle);
        $next = strpos($blob, 'Schema::create(', $start + strlen($needle));
        $end = $next === false ? strlen($blob) : $next;

        return substr($blob, $start, $end - $start);
    }

    private function diskBlock(string $config, string $disk): string
    {
        $needle = "'".$disk."' => [";
        $start = strpos($config, $needle);
        $this->assertNotFalse($start, $needle);
        $next = strpos($config, "\n        '", $start + strlen($needle));
        $this->assertNotFalse($next);

        return substr($config, $start, $next - $start);
    }
}
