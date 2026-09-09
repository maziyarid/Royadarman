<?php

namespace Tests\Unit;

use App\Domain\Cases\Enums\CaseStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CaseStatusTest extends TestCase
{
    #[Test]
    public function it_allows_only_explicit_transitions(): void
    {
        $this->assertTrue(CaseStatus::Submitted->canTransitionTo(CaseStatus::AwaitingContact));
        $this->assertTrue(CaseStatus::Resolved->canTransitionTo(CaseStatus::Closed));
        $this->assertFalse(CaseStatus::Submitted->canTransitionTo(CaseStatus::Closed));
        $this->assertFalse(CaseStatus::Closed->canTransitionTo(CaseStatus::InCoordination));
    }
}

