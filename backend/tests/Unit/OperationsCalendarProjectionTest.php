<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** No database. The oracle is the proof when PHPUnit is absent. */
final class OperationsCalendarProjectionTest extends TestCase
{
    public function test_client_cannot_recompute_the_server_window(): void
    {
        require_once dirname(__DIR__).'/Support/OperationsCalendarProjectionOracle.php';
        $this->assertSame([], operations_projection_failures());
    }
}
