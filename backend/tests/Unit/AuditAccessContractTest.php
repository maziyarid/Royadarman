<?php

namespace Tests\Unit;

use App\Domain\Audit\AuditAccessContract;
use PHPUnit\Framework\TestCase;

final class AuditAccessContractTest extends TestCase
{
    public function test_audit_table_has_no_tenant_or_retention_column_and_the_model_encrypts_payloads(): void
    {
        $blob = $this->migrations();
        $block = $this->createBlock($blob, 'audit_events');

        foreach (AuditAccessContract::currentColumns() as $column) {
            $this->assertStringContainsString($column, $block);
        }

        $this->assertStringNotContainsString('clinic_id', $block);
        $this->assertStringNotContainsString('tenant_id', $block);
        $this->assertStringNotContainsString('retention', $block);
        $this->assertNull(AuditAccessContract::acceptedRetentionDays());
        $this->assertSame('proposed_not_wired', AuditAccessContract::STATUS);

        $model = (string) file_get_contents(dirname(__DIR__, 2).'/app/Models/AuditEvent.php');
        $this->assertStringContainsString("'reason' => 'encrypted'", $model);
        $this->assertStringContainsString("'context' => 'encrypted:array'", $model);
        $this->assertStringContainsString("['context']", $model);
    }

    public function test_auto_assignment_no_longer_inserts_plaintext_audit_context(): void
    {
        $source = (string) file_get_contents(
            dirname(__DIR__, 2).'/app/Domain/Coordination/Services/CoordinatorAssignment.php',
        );

        $this->assertStringNotContainsString("DB::table('audit_events')->insert", $source);
        $this->assertStringNotContainsString('json_encode(', $source);
        $this->assertStringContainsString('AuditEvent::query()->create', $source);
        $this->assertStringContainsString("'coordinator_user_id'", $source);
    }

    public function test_no_title_authorises_a_clinical_read(): void
    {
        foreach (['owner', 'tech_admin', 'superadmin', 'dentist', 'clinician', 'coordinator', ''] as $title) {
            $decision = AuditAccessContract::authorisesClinicalRead($title);
            $this->assertFalse($decision->allowed);
            $this->assertSame('privileged_read_not_authorised', $decision->reason);
        }
    }

    public function test_payload_redaction_fails_closed_and_a_clean_row_is_still_not_a_writer(): void
    {
        $clean = [
            'action' => 'case.coordinator_auto_assigned',
            'result' => 'success',
            'correlation_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'reason' => 'least_loaded_active_coordinator',
            'context' => ['coordinator_user_id' => 7],
        ];

        $passed = AuditAccessContract::assess($clean);
        $this->assertFalse($passed->allowed);
        $this->assertSame('redaction_passed_not_a_writer', $passed->reason);

        $this->assertSame('retention_not_decided', AuditAccessContract::assess($clean + ['retention_days' => 365])->reason);
        $this->assertSame('tenant_column_absent', AuditAccessContract::assess($clean + ['clinic_id' => 'clinic-a'])->reason);
        $this->assertSame('tenant_column_absent', AuditAccessContract::assess($clean + ['tenant_id' => 'clinic-a'])->reason);
        $this->assertSame('action_required', AuditAccessContract::assess(['action' => '', 'result' => 'success', 'correlation_id' => 'x'])->reason);
        $this->assertSame('correlation_required', AuditAccessContract::assess(['action' => 'case.viewed', 'result' => 'denied', 'correlation_id' => ''])->reason);
        $this->assertSame('result_unspecified', AuditAccessContract::assess(['action' => 'case.viewed', 'result' => 'ok', 'correlation_id' => 'x'])->reason);
        $preencoded = $clean;
        $preencoded['context'] = '{"coordinator_user_id":7}';
        $this->assertSame('context_must_be_structured', AuditAccessContract::assess($preencoded)->reason);
        $this->assertSame(
            'forbidden_field',
            AuditAccessContract::assess(['action' => 'note.added', 'result' => 'success', 'correlation_id' => 'x', 'context' => ['message_body' => 'hello']])->reason,
        );
        $this->assertSame(
            'phone_not_allowed',
            AuditAccessContract::assess(['action' => 'case.viewed', 'result' => 'denied', 'correlation_id' => 'x', 'reason' => '09120000000'])->reason,
        );
        $this->assertSame(
            'email_not_allowed',
            AuditAccessContract::assess(['action' => 'staff.created', 'result' => 'success', 'correlation_id' => 'x', 'context' => ['label' => 'person@example.com']])->reason,
        );
        $this->assertSame(
            'forbidden_field',
            AuditAccessContract::assess(['action' => 'document.read', 'result' => 'success', 'correlation_id' => 'x', 'context' => ['observations' => 'caries']])->reason,
        );
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
}
