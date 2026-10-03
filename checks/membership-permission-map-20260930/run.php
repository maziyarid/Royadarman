<?php

declare(strict_types=1);

$root = $argv[1] ?? '';
$app = '/home/royadarman/apps/royadarman-backend';
$allowed = [
    $app.'/app/Domain/Identity/Authorization',
    '/home/royadarman/checks/membership-permission-map-20260930/src',
];
if (! in_array($root, $allowed, true)) {
    fwrite(STDERR, "refusing root\n");
    exit(2);
}

require $app.'/app/Domain/Identity/Enums/UserRole.php';
require $root.'/MembershipPermission.php';
require $root.'/MembershipPermissionMap.php';

use App\Domain\Identity\Authorization\MembershipPermissionMap;
use App\Domain\Identity\Enums\UserRole;

$passed = 0;
$failed = 0;

function check(bool $condition, string $message): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        return;
    }
    $failed++;
    fwrite(STDERR, "FAIL {$message}\n");
}

check(MembershipPermissionMap::knownMembershipRoles() === ['reviewer', 'contact'], 'known roles');

$assign = [
    [false, UserRole::Clinician, 'reviewer', false, true, 'account_inactive'],
    [false, null, 'reviewer', true, true, 'unknown_account_role'],
    [false, UserRole::Clinician, 'admin', true, true, 'unknown_membership_role'],
    [false, UserRole::Clinician, '', true, true, 'unknown_membership_role'],
    [false, UserRole::Patient, 'reviewer', true, true, 'account_role_cannot_join_clinic'],
    [false, UserRole::Patient, 'contact', true, true, 'account_role_cannot_join_clinic'],
    [false, UserRole::Coordinator, 'reviewer', true, true, 'account_role_cannot_join_clinic'],
    [false, UserRole::Coordinator, 'contact', true, false, 'account_role_cannot_join_clinic'],
    [false, UserRole::Owner, 'reviewer', true, true, 'account_role_cannot_join_clinic'],
    [false, UserRole::Owner, 'contact', true, true, 'account_role_cannot_join_clinic'],
    [false, UserRole::TechnicalAdministrator, 'reviewer', true, true, 'account_role_cannot_join_clinic'],
    [false, UserRole::TechnicalAdministrator, 'contact', true, false, 'account_role_cannot_join_clinic'],
    [false, UserRole::ClinicRepresentative, 'reviewer', true, true, 'reviewer_requires_clinician'],
    [false, UserRole::Clinician, 'reviewer', true, false, 'reviewer_requires_current_credential'],
    [true, UserRole::Clinician, 'reviewer', true, true, 'assigned_reviewer'],
    [true, UserRole::Clinician, 'contact', true, false, 'assigned_contact'],
    [true, UserRole::ClinicRepresentative, 'contact', true, false, 'assigned_contact'],
    [true, UserRole::ClinicRepresentative, 'contact', true, true, 'assigned_contact'],
];

foreach ($assign as $i => [$allow, $role, $membership, $active, $credential, $reason]) {
    $decision = MembershipPermissionMap::assignMembership($role, $membership, $active, $credential);
    check($decision->allowed === $allow, "assign {$i} allowed");
    check($decision->reason === $reason, "assign {$i} reason {$decision->reason}");
}

$roles = [
    null,
    UserRole::Patient,
    UserRole::Coordinator,
    UserRole::Clinician,
    UserRole::ClinicRepresentative,
    UserRole::Owner,
    UserRole::TechnicalAdministrator,
];
$memberships = [null, '', 'reviewer', 'contact', 'owner', 'support'];
foreach ($roles as $role) {
    foreach ($memberships as $membership) {
        foreach ([false, true] as $active) {
            foreach ([false, true] as $credential) {
                $decision = MembershipPermissionMap::clinicalRecordFromMembership($role, $membership, $active, $credential);
                check($decision->allowed === false, 'clinical allowed');
                $expected = $role === null ? 'unknown_account_role' : (
                    in_array($role, [UserRole::Patient, UserRole::Coordinator, UserRole::Owner, UserRole::TechnicalAdministrator], true)
                        ? 'account_role_cannot_open_clinical_record'
                        : 'membership_is_not_clinical_access'
                );
                check($decision->reason === $expected, 'clinical reason '.$decision->reason);
            }
            $support = MembershipPermissionMap::supportWorkspaceFromMembership($role, $membership, $active);
            check($support->allowed === false, 'support allowed');
            check($support->reason === 'membership_does_not_grant_support_workspace', 'support reason');
        }
    }
}

$strongest = MembershipPermissionMap::clinicalRecordFromMembership(UserRole::Clinician, 'reviewer', true, true);
check($strongest->allowed === false && $strongest->reason === 'membership_is_not_clinical_access', 'strongest clinical deny');
$supportEdge = MembershipPermissionMap::supportWorkspaceFromMembership(UserRole::Coordinator, 'contact', true);
check($supportEdge->allowed === false, 'coordinator contact support deny');

if ($failed !== 0) {
    fwrite(STDERR, "passed={$passed} failed={$failed}\n");
    exit(1);
}

echo "passed={$passed} failed=0\n";
