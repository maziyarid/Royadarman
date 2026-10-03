<?php

namespace App\Domain\Finance;

use App\Domain\Identity\Authorization\MembershipPermission;

/**
 * Records the money fields that exist today. It does not store an amount,
 * post a ledger line, or convert toman to rial.
 */
final class FinanceIntegerContract
{
    public const STATUS = 'proposed_not_wired';

    /**
     * @return list<string>
     */
    public static function storedLabels(): array
    {
        return [
            'patient_cases.budget_band',
            'patient_cases.currency',
            'patient_cases.budget_input_unit',
            'review_revisions.budget_band',
        ];
    }

    /**
     * @return list<string>
     */
    public static function budgetBands(): array
    {
        return ['economic', 'balanced', 'flexible', 'call'];
    }

    /**
     * @return list<string>
     */
    public static function inputUnits(): array
    {
        return ['toman', 'irr'];
    }

    public static function storedCurrencyCode(): string
    {
        return 'IRR';
    }

    public static function minorUnitFactor(): ?int
    {
        return null;
    }

    public static function interpretBand(string $band): MembershipPermission
    {
        if (! in_array($band, self::budgetBands(), true)) {
            return new MembershipPermission(false, 'unknown_budget_band');
        }

        return new MembershipPermission(false, 'band_is_not_an_amount');
    }

    public static function acceptAmount(int|float|string $amount): MembershipPermission
    {
        if (is_float($amount) || (is_string($amount) && str_contains($amount, '.'))) {
            return new MembershipPermission(false, 'float_amount_rejected');
        }

        return new MembershipPermission(false, 'amount_column_absent');
    }

    public static function convertUnit(string $from, string $to): MembershipPermission
    {
        if (! in_array($from, self::inputUnits(), true) || ! in_array($to, self::inputUnits(), true)) {
            return new MembershipPermission(false, 'unknown_input_unit');
        }

        return new MembershipPermission(false, 'conversion_not_stored');
    }

    public static function postLedger(int $debit, int $credit): MembershipPermission
    {
        if ($debit !== $credit) {
            return new MembershipPermission(false, 'ledger_unbalanced');
        }

        return new MembershipPermission(false, 'ledger_table_absent');
    }

    public static function merchantReady(): MembershipPermission
    {
        return new MembershipPermission(false, 'merchant_not_configured');
    }
}
