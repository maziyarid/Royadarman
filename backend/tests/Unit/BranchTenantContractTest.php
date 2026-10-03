<?php

namespace Tests\Unit;

use App\Domain\Tenancy\BranchTenantContract;
use PHPUnit\Framework\TestCase;

final class BranchTenantContractTest extends TestCase
{
    public function test_current_migrations_have_clinic_tenancy_and_no_branch_table(): void
    {
        $blob = $this->migrations();

        $this->assertDoesNotMatchRegularExpression("/Schema::create\\(\\s*'branches'/", $blob);
        $this->assertDoesNotMatchRegularExpression("/Schema::create\\(\\s*'organisations'/", $blob);
        $this->assertDoesNotMatchRegularExpression("/Schema::create\\(\\s*'appointments'/", $blob);
        $this->assertDoesNotMatchRegularExpression("/Schema::create\\(\\s*'slots'/", $blob);
        $this->assertMatchesRegularExpression("/Schema::create\\(\\s*'clinics'/", $blob);

        foreach (['patient_cases', 'clinical_documents', 'review_revisions', 'coordination_tasks', 'case_assignments', 'support_conversations'] as $table) {
            $block = $this->createBlock($blob, $table);
            $this->assertStringNotContainsString('clinic_id', $block, $table);
            $this->assertStringNotContainsString('branch_id', $block, $table);
        }

        foreach (['clinic_memberships', 'clinic_service_capabilities', 'referral_proposals', 'referral_grants', 'referral_lifecycle_events'] as $table) {
            $this->assertStringContainsString('clinic_id', $this->createBlock($blob, $table), $table);
        }

        $grants = $this->createBlock($blob, 'referral_grants');
        $this->assertStringContainsString('proposal_id', $grants);
        $this->assertStringContainsString('case_id', $grants);
        $this->assertStringNotContainsString("['proposal_id', 'clinic_id', 'case_id']", $grants);
    }

    public function test_migration_stays_closed_and_create_database_denial_does_not_widen_privileges(): void
    {
        $this->assertSame('proposed_not_migrated', BranchTenantContract::STATUS);
        $this->assertSame('clinic', BranchTenantContract::TENANT_GRAIN);
        $this->assertFalse(BranchTenantContract::createDatabaseDenialExpandsPrivileges());
        $this->assertNull(BranchTenantContract::acceptedRecoveryObjectives());

        $this->assertSame('fresh_backup_not_verified', BranchTenantContract::migrationGate(false, false)->reason);
        $this->assertSame('fresh_backup_not_verified', BranchTenantContract::migrationGate(false, true)->reason);
        $this->assertSame('restore_target_not_proven', BranchTenantContract::migrationGate(true, false)->reason);

        $ready = BranchTenantContract::migrationGate(true, true);
        $this->assertFalse($ready->allowed);
        $this->assertSame('migration_not_in_this_contract', $ready->reason);
    }

    public function test_a_branch_id_without_its_clinic_is_rejected_and_a_pair_still_cannot_be_stored(): void
    {
        $this->assertSame(['clinic_id', 'id'], BranchTenantContract::proposedBranchPrimaryKey());
        $this->assertSame(['clinic_id', 'branch_id'], BranchTenantContract::proposedBranchChildForeignKey());
        $this->assertNotSame(['branch_id'], BranchTenantContract::proposedBranchChildForeignKey());

        $this->assertSame('branch_without_clinic', BranchTenantContract::scope(null, 'branch-south')->reason);
        $this->assertSame('branch_without_clinic', BranchTenantContract::scope('', 'branch-south')->reason);
        $this->assertSame('branch_table_absent', BranchTenantContract::scope('clinic-north', 'branch-south')->reason);
        $this->assertSame('clinic_scope_not_a_migration', BranchTenantContract::scope('clinic-north', null)->reason);
        $this->assertSame('tenant_scope_missing', BranchTenantContract::scope(null, null)->reason);

        foreach ([null, '', 'clinic-north'] as $clinic) {
            foreach ([null, '', 'branch-south'] as $branch) {
                $this->assertFalse(BranchTenantContract::scope($clinic, $branch)->allowed);
            }
        }
    }

    public function test_a_case_does_not_take_one_clinic_and_a_mismatched_grant_fails_closed(): void
    {
        $this->assertSame('case_is_not_single_clinic_owned', BranchTenantContract::proposedCaseColumn('clinic_id')->reason);
        $this->assertSame('case_is_not_single_clinic_owned', BranchTenantContract::proposedCaseColumn('branch_id')->reason);
        $this->assertSame('case_column_not_in_this_contract', BranchTenantContract::proposedCaseColumn('currency')->reason);
        $this->assertFalse(BranchTenantContract::proposedCaseColumn('clinic_id')->allowed);

        $this->assertSame(
            'grant_proposal_mismatch',
            BranchTenantContract::grantFollowsProposal('clinic-a', 'clinic-b', 'case-1', 'case-1')->reason,
        );
        $this->assertSame(
            'grant_proposal_mismatch',
            BranchTenantContract::grantFollowsProposal('clinic-a', 'clinic-a', 'case-1', 'case-2')->reason,
        );
        $this->assertSame(
            'linkage_incomplete',
            BranchTenantContract::grantFollowsProposal('clinic-a', '', 'case-1', 'case-1')->reason,
        );

        $match = BranchTenantContract::grantFollowsProposal('clinic-a', 'clinic-a', 'case-1', 'case-1');
        $this->assertFalse($match->allowed);
        $this->assertSame('linkage_matches_but_not_activated', $match->reason);
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
