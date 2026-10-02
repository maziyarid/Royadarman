<?php

declare(strict_types=1);

namespace Tests\Unit;

use PDO;
use PHPUnit\Framework\TestCase;

/** Throwaway InnoDB only. Not a migration. */
final class SchedulingRaceTest extends TestCase
{
    public function test_last_slot_lock_and_idempotency(): void
    {
        $dsn = getenv('G14_DSN') ?: '';
        if ($dsn === '' || !str_contains($dsn, 'g14_synthetic') || str_contains($dsn, '3306')) {
            $this->markTestSkipped('G14_DSN throwaway database is not configured');
        }
        require_once dirname(__DIR__).'/Support/SchedulingRaceOracle.php';
        $pdo = new PDO($dsn, getenv('G14_USER') ?: 'root', getenv('G14_PASS') ?: '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $failures = (new SchedulingRaceOracle($pdo, PHP_BINARY))->run();
        $this->assertSame([], $failures);
    }
}
