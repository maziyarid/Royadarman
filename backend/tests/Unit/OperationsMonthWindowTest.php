<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__).'/Support/OperationsMonthWindowOracle.php';

/** No database. The CLI oracle is the run that was actually observed. */
final class OperationsMonthWindowTest extends TestCase
{
    public function test_controller_uses_the_shared_jalali_window(): void
    {
        $this->assertSame([], operations_month_window_failures());
    }
}
