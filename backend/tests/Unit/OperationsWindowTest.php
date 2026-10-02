<?php

declare(strict_types=1);

namespace Tests\Unit;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__).'/Support/OperationsWindowOracle.php';

/**
 * Runs only when G12_DSN names the throwaway database g12_synthetic.
 * Not a production or HTTP test. PHPUnit itself was not present on the live checkout.
 */
final class OperationsWindowTest extends TestCase
{
    public function test_half_open_tehran_windows_and_assignment_isolation(): void
    {
        $dsn = getenv('G12_DSN') ?: '';
        if ($dsn === '' || !str_contains($dsn, 'g12_synthetic') || str_contains($dsn, '3306')) {
            $this->markTestSkipped('G12_DSN throwaway database is not configured');
        }

        $pdo = new PDO($dsn, getenv('G12_USER') ?: 'root', getenv('G12_PASS') ?: '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $pdo->exec("SET time_zone = '+00:00'");

        $this->assertSame([], operations_window_failures($pdo));
    }
}
