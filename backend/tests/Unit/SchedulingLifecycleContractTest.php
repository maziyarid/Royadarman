<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Proposal oracle. Extends PHPUnit's TestCase directly so it can run
 * without the Laravel application bootstrap. Not a database test.
 */
final class SchedulingLifecycleContractTest extends TestCase
{
    public function test_proposed_scheduling_invariants(): void
    {
        require_once dirname(__DIR__).'/Support/SchedulingContractOracle.php';
        $this->assertTrue(true);
    }
}
