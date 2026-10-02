<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__).'/Support/OperationsWeekWindowOracle.php';

/** No database. The CLI oracle is the run that was actually observed. */
final class OperationsWeekWindowTest extends TestCase
{
    public function test_week_is_saturday_first_and_server_owned(): void
    {
        $this->assertSame([], operations_week_window_failures());
    }
}
