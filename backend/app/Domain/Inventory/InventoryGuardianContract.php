<?php

namespace App\Domain\Inventory;

use App\Domain\Identity\Authorization\MembershipPermission;

/**
 * Records that stock, guardians, and patient merge are absent.
 * It does not define a stock rule or a merge policy.
 */
final class InventoryGuardianContract
{
    public const STATUS = 'proposed_not_wired';

    public static function acceptStock(string $sku, int $quantity, ?string $expiresOn): MembershipPermission
    {
        return new MembershipPermission(false, 'stock_table_absent');
    }

    public static function acceptPurchase(int $amountMinor): MembershipPermission
    {
        return new MembershipPermission(false, 'purchase_table_absent');
    }

    public static function linkGuardian(string $patientUserId, string $guardianUserId): MembershipPermission
    {
        return new MembershipPermission(false, 'guardian_column_absent');
    }

    public static function mergePatients(string $fromUserId, string $intoUserId): MembershipPermission
    {
        if ($fromUserId === '' || $intoUserId === '') {
            return new MembershipPermission(false, 'patient_not_named');
        }

        if ($fromUserId === $intoUserId) {
            return new MembershipPermission(false, 'merge_same_patient');
        }

        return new MembershipPermission(false, 'patient_merge_not_defined');
    }

    public static function cmsTagMergeIsPatientMerge(): bool
    {
        return false;
    }

    public static function sessionInventoryIsStock(): bool
    {
        return false;
    }

    public static function grantExpiryIsStockExpiry(): bool
    {
        return false;
    }
}
