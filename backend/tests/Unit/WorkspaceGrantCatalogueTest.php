<?php

namespace Tests\Unit;

use App\Domain\Identity\Authorization\WorkspaceGrantCatalogue;
use App\Domain\Identity\Enums\UserRole;
use PHPUnit\Framework\TestCase;

final class WorkspaceGrantCatalogueTest extends TestCase
{
    public function test_account_readers_stay_on_the_six_current_roles(): void
    {
        $expected = ['patient', 'coordinator', 'clinician', 'clinic_rep', 'owner', 'tech_admin'];

        $this->assertSame($expected, WorkspaceGrantCatalogue::preservedAccountRoles());
        $this->assertSame(
            $expected,
            array_map(static fn (UserRole $role): string => $role->value, UserRole::cases()),
        );
        $this->assertCount(6, UserRole::cases());
    }

    public function test_catalogue_names_the_twelve_roadmap_workspaces_and_no_others(): void
    {
        $ids = WorkspaceGrantCatalogue::workspaceIds();

        $this->assertCount(12, $ids);
        $this->assertSame($ids, array_values(array_unique($ids)));
        $this->assertSame([
            'owner',
            'developer',
            'superadmin',
            'supervisor',
            'receptionist',
            'accountant',
            'customer_support',
            'treatment_specialist',
            'clinic_manager',
            'dentist',
            'clinical_staff',
            'patient',
        ], $ids);
        $this->assertNotContains('guardian', $ids);
        $this->assertNotContains('tech_admin', $ids);
        $this->assertNotContains('coordinator', $ids);
        $this->assertNotContains('clinic_rep', $ids);
        $this->assertSame('proposed_not_activated', WorkspaceGrantCatalogue::STATUS);
    }

    public function test_slash_pairs_do_not_alias_coordinator_clinic_rep_or_tech_admin(): void
    {
        $pairs = WorkspaceGrantCatalogue::roadmapSlashPairs();

        $this->assertSame([
            'owner' => 'owner',
            'dentist' => 'clinician',
            'patient' => 'patient',
        ], $pairs);

        $aliasedRoles = array_values($pairs);
        $this->assertNotContains('coordinator', $aliasedRoles);
        $this->assertNotContains('clinic_rep', $aliasedRoles);
        $this->assertNotContains('tech_admin', $aliasedRoles);
        foreach (array_keys($pairs) as $workspaceId) {
            $this->assertContains($workspaceId, WorkspaceGrantCatalogue::workspaceIds());
        }
    }

    public function test_branch_organisation_and_team_are_not_schema_backed(): void
    {
        $this->assertSame(
            ['personal', 'organisation', 'clinic', 'branch', 'team', 'assignment'],
            WorkspaceGrantCatalogue::proposedScopeKinds(),
        );
        $this->assertSame(['personal', 'clinic'], WorkspaceGrantCatalogue::schemaBackedScopeKinds());
        $this->assertNotContains('branch', WorkspaceGrantCatalogue::schemaBackedScopeKinds());
    }

    public function test_no_workspace_title_grants_clinical_signing(): void
    {
        foreach (WorkspaceGrantCatalogue::workspaceIds() as $workspaceId) {
            $this->assertFalse(WorkspaceGrantCatalogue::titleGrantsClinicalSigning($workspaceId));
        }

        foreach (['owner', 'superadmin', 'developer', 'dentist', 'tech_admin', ''] as $title) {
            $this->assertFalse(WorkspaceGrantCatalogue::titleGrantsClinicalSigning($title));
        }
    }

    public function test_every_workspace_action_and_lifetime_stays_denied(): void
    {
        $now = '2026-10-01T06:40:00Z';
        $future = '2099-01-01T00:00:00Z';
        $past = '2020-01-01T00:00:00Z';
        $seen = 0;

        foreach (WorkspaceGrantCatalogue::workspaceIds() as $workspaceId) {
            foreach (WorkspaceGrantCatalogue::actions() as $action) {
                $active = WorkspaceGrantCatalogue::evaluate($workspaceId, $action, false, $future, $now);
                $open = WorkspaceGrantCatalogue::evaluate($workspaceId, $action, false, null, $now);
                $revoked = WorkspaceGrantCatalogue::evaluate($workspaceId, $action, true, $future, $now);
                $expired = WorkspaceGrantCatalogue::evaluate($workspaceId, $action, false, $past, $now);
                $revokedAndExpired = WorkspaceGrantCatalogue::evaluate($workspaceId, $action, true, $past, $now);

                $this->assertFalse($active->allowed);
                $this->assertFalse($open->allowed);
                $this->assertFalse($revoked->allowed);
                $this->assertFalse($expired->allowed);
                $this->assertFalse($revokedAndExpired->allowed);
                $this->assertSame('grant_not_activated', $active->reason);
                $this->assertSame('grant_not_activated', $open->reason);
                $this->assertSame('grant_revoked', $revoked->reason);
                $this->assertSame('grant_expired', $expired->reason);
                $this->assertSame('grant_revoked', $revokedAndExpired->reason);
                $seen += 5;
            }
        }

        $this->assertSame(12 * 8 * 5, $seen);
        $this->assertSame(
            'grant_not_activated',
            WorkspaceGrantCatalogue::evaluate('dentist', 'clinical_sign', false, $future, $now)->reason,
        );
        $this->assertSame(
            'grant_not_activated',
            WorkspaceGrantCatalogue::evaluate('owner', 'clinical_sign', false, null, $now)->reason,
        );
        $this->assertSame(
            'grant_not_activated',
            WorkspaceGrantCatalogue::evaluate('superadmin', 'clinical_sign', false, null, $now)->reason,
        );
    }

    public function test_unknown_workspace_action_and_expiry_fail_closed(): void
    {
        $now = '2026-10-01T06:40:00Z';

        $this->assertSame(
            'unknown_workspace',
            WorkspaceGrantCatalogue::evaluate('tech_admin', 'clinical_sign', false, null, $now)->reason,
        );
        $this->assertSame(
            'unknown_workspace',
            WorkspaceGrantCatalogue::evaluate('coordinator', 'tenant_admin', false, null, $now)->reason,
        );
        $this->assertSame(
            'unknown_action',
            WorkspaceGrantCatalogue::evaluate('dentist', 'impersonate', false, null, $now)->reason,
        );
        $this->assertSame(
            'invalid_expiry',
            WorkspaceGrantCatalogue::evaluate('dentist', 'clinical_read', false, 'not-a-date', $now)->reason,
        );
        $this->assertSame(
            'invalid_expiry',
            WorkspaceGrantCatalogue::evaluate('dentist', 'clinical_read', false, 'tomorrow', $now)->reason,
        );
        $this->assertSame(
            'invalid_expiry',
            WorkspaceGrantCatalogue::evaluate('dentist', 'clinical_read', false, 'yesterday', $now)->reason,
        );
        $this->assertSame(
            'grant_expired',
            WorkspaceGrantCatalogue::evaluate('dentist', 'clinical_read', false, $now, $now)->reason,
        );
        $this->assertSame(
            'invalid_expiry',
            WorkspaceGrantCatalogue::evaluate('dentist', 'clinical_read', false, '2026-10-02 00:00:00', $now)->reason,
        );
        $this->assertSame(
            'invalid_expiry',
            WorkspaceGrantCatalogue::evaluate('dentist', 'clinical_read', false, '2099-01-01T00:00:00Z', '2026-10-01 06:40:00')->reason,
        );
        $this->assertSame(
            'grant_not_activated',
            WorkspaceGrantCatalogue::evaluate(
                'dentist',
                'clinical_read',
                false,
                '2099-01-01T00:00:00.5+03:30',
                '2026-10-01T06:40:00+03:30',
            )->reason,
        );
        $this->assertFalse(
            WorkspaceGrantCatalogue::evaluate('dentist', 'clinical_sign', false, 'not-a-date', $now)->allowed,
        );
    }
}
