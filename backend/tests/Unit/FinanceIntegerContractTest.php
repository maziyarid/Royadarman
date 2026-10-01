<?php

namespace Tests\Unit;

use App\Domain\Finance\FinanceIntegerContract;
use PHPUnit\Framework\TestCase;

final class FinanceIntegerContractTest extends TestCase
{
    public function test_schema_stores_labels_and_no_money_amount_or_ledger(): void
    {
        $blob = $this->migrations();

        $this->assertStringContainsString("\$table->string('budget_band', 30);", $blob);
        $this->assertStringContainsString("\$table->string('currency', 3)->default('IRR');", $blob);
        $this->assertStringContainsString("\$table->string('budget_input_unit', 8)->default('toman');", $blob);
        $this->assertStringContainsString("\$table->string('budget_band', 30)->nullable();", $blob);

        foreach (['invoices', 'payments', 'ledger_entries', 'receipts', 'refunds', 'cheques', 'instalments'] as $absent) {
            $this->assertDoesNotMatchRegularExpression("/Schema::create\(\s*'".$absent."'/", $blob);
        }

        preg_match_all("/\\\$table->(?:decimal|float|double)\\('([^']+)'/", $blob, $scales);
        $this->assertSame(['latitude', 'longitude'], $scales[1]);

        preg_match_all("/\\\$table->(?:unsignedInteger|integer|unsignedBigInteger|bigInteger)\\('([^']+)'/", $blob, $integers);
        foreach ($integers[1] as $column) {
            $this->assertDoesNotMatchRegularExpression('/amount|price|fee|minor|rial|toman/', $column);
        }

        $this->assertNull(FinanceIntegerContract::minorUnitFactor());
        $this->assertSame('IRR', FinanceIntegerContract::storedCurrencyCode());
        $this->assertSame('proposed_not_wired', FinanceIntegerContract::STATUS);
        $this->assertSame([
            'patient_cases.budget_band',
            'patient_cases.currency',
            'patient_cases.budget_input_unit',
            'review_revisions.budget_band',
        ], FinanceIntegerContract::storedLabels());
    }

    public function test_draft_writes_the_currency_code_and_not_an_amount(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/Api/V1/CaseController.php');
        $this->assertStringContainsString("'budget_input_unit' => ['required', 'in:toman,irr']", $source);
        $this->assertStringContainsString("'currency' => 'IRR'", $source);
        $this->assertStringNotContainsString('amount', strtolower($source));
    }

    public function test_a_band_a_float_and_a_balanced_pair_do_not_become_money(): void
    {
        foreach (FinanceIntegerContract::budgetBands() as $band) {
            $decision = FinanceIntegerContract::interpretBand($band);
            $this->assertFalse($decision->allowed);
            $this->assertSame('band_is_not_an_amount', $decision->reason);
        }

        $this->assertSame('unknown_budget_band', FinanceIntegerContract::interpretBand('1500000')->reason);
        $this->assertSame('float_amount_rejected', FinanceIntegerContract::acceptAmount(10.5)->reason);
        $this->assertSame('float_amount_rejected', FinanceIntegerContract::acceptAmount('10.50')->reason);
        $this->assertSame('amount_column_absent', FinanceIntegerContract::acceptAmount(1500000)->reason);
        $this->assertSame('amount_column_absent', FinanceIntegerContract::acceptAmount(0)->reason);

        $this->assertSame('conversion_not_stored', FinanceIntegerContract::convertUnit('toman', 'irr')->reason);
        $this->assertSame('unknown_input_unit', FinanceIntegerContract::convertUnit('usd', 'irr')->reason);

        $this->assertSame('ledger_unbalanced', FinanceIntegerContract::postLedger(100, 90)->reason);
        $balanced = FinanceIntegerContract::postLedger(100, 100);
        $this->assertFalse($balanced->allowed);
        $this->assertSame('ledger_table_absent', $balanced->reason);

        $merchant = FinanceIntegerContract::merchantReady();
        $this->assertFalse($merchant->allowed);
        $this->assertSame('merchant_not_configured', $merchant->reason);
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
}
