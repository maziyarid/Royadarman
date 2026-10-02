<?php

declare(strict_types=1);

namespace Tests\Unit;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__).'/Support/CalendarNotificationOracle.php';

/** Not an HTTP test and not a call to the live SMS job. */
final class CalendarNotificationPolicyTest extends TestCase
{
    public function test_correlation_suppression_and_retry_on_throwaway_database(): void
    {
        $dsn = getenv('G13_DSN') ?: '';
        if ($dsn === '' || !str_contains($dsn, 'g13_synthetic') || str_contains($dsn, '3306')) {
            $this->markTestSkipped('G13_DSN throwaway database is not configured');
        }
        $pdo = new PDO($dsn, getenv('G13_USER') ?: 'root', getenv('G13_PASS') ?: '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $pdo->exec("SET time_zone = '+00:00'");
        $this->assertSame([], calendar_notification_failures($pdo));
    }
}
