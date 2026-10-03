<?php

namespace Tests\Unit;

use App\Domain\Inventory\InventoryGuardianContract;
use PHPUnit\Framework\TestCase;

final class InventoryGuardianContractTest extends TestCase
{
    public function test_migrations_have_no_stock_guardian_or_patient_merge_table(): void
    {
        $blob = $this->migrations();

        foreach ([
            'stock_items',
            'stock_batches',
            'purchase_orders',
            'guardians',
            'guardian_relationships',
            'patient_merges',
            'maintenance_records',
        ] as $absent) {
            $this->assertDoesNotMatchRegularExpression("/Schema::create\(\s*'".$absent."'/", $blob);
        }

        $batches = $this->createBlock($blob, 'job_batches');
        $this->assertStringContainsString('total_jobs', $batches);
        $this->assertStringNotContainsString('sku', $batches);
        $this->assertStringNotContainsString('quantity', $batches);

        $users = $this->createBlock($blob, 'users');
        $cases = $this->createBlock($blob, 'patient_cases');
        $this->assertStringNotContainsString('guardian', $users);
        $this->assertStringNotContainsString('guardian', $cases);
        $this->assertStringNotContainsString('guardian', strtolower($blob));

        $this->assertStringContainsString('expires_at', $blob);
        $this->assertSame('proposed_not_wired', InventoryGuardianContract::STATUS);
    }

    public function test_existing_merge_and_inventory_names_are_not_these_domains(): void
    {
        $tags = (string) file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/Api/V1/CmsTagController.php');
        $this->assertStringContainsString("'merged_into'", $tags);
        $this->assertStringContainsString('cms_post_tag', $tags);
        $this->assertFalse(InventoryGuardianContract::cmsTagMergeIsPatientMerge());

        $sessions = (string) file_get_contents(dirname(__DIR__, 2).'/app/Domain/Identity/Services/SessionInventoryService.php');
        $this->assertStringNotContainsString('sku', strtolower($sessions));
        $this->assertStringNotContainsString('stock', strtolower($sessions));
        $this->assertFalse(InventoryGuardianContract::sessionInventoryIsStock());
        $this->assertFalse(InventoryGuardianContract::grantExpiryIsStockExpiry());
    }

    public function test_stock_purchase_guardian_and_merge_stay_closed(): void
    {
        foreach ([
            InventoryGuardianContract::acceptStock('sku-1', 1, '2026-12-01'),
            InventoryGuardianContract::acceptStock('', 0, null),
            InventoryGuardianContract::acceptStock('sku-1', -1, '2020-01-01'),
        ] as $decision) {
            $this->assertFalse($decision->allowed);
            $this->assertSame('stock_table_absent', $decision->reason);
        }

        $purchase = InventoryGuardianContract::acceptPurchase(0);
        $this->assertFalse($purchase->allowed);
        $this->assertSame('purchase_table_absent', $purchase->reason);

        $guardian = InventoryGuardianContract::linkGuardian('patient-1', 'adult-1');
        $this->assertFalse($guardian->allowed);
        $this->assertSame('guardian_column_absent', $guardian->reason);

        $this->assertSame('patient_not_named', InventoryGuardianContract::mergePatients('', 'patient-2')->reason);
        $this->assertSame('merge_same_patient', InventoryGuardianContract::mergePatients('patient-1', 'patient-1')->reason);

        $merge = InventoryGuardianContract::mergePatients('patient-1', 'patient-2');
        $this->assertFalse($merge->allowed);
        $this->assertSame('patient_merge_not_defined', $merge->reason);
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
