<?php

namespace Tests\Unit;

use App\Domain\Cases\Enums\HomeServiceStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class HomeServiceStatusTest extends TestCase
{
    #[Test]
    public function it_allows_only_explicit_transitions_per_section_18_lifecycle(): void
    {
        $this->assertTrue(HomeServiceStatus::Requested->canTransitionTo(HomeServiceStatus::AreaVerified));
        $this->assertTrue(HomeServiceStatus::AreaVerified->canTransitionTo(HomeServiceStatus::CoordinatorReview));
        $this->assertTrue(HomeServiceStatus::CoordinatorReview->canTransitionTo(HomeServiceStatus::ProviderRequested));
        $this->assertTrue(HomeServiceStatus::ProviderRequested->canTransitionTo(HomeServiceStatus::ProviderAccepted));
        $this->assertTrue(HomeServiceStatus::ProviderAccepted->canTransitionTo(HomeServiceStatus::PatientConfirmed));
        $this->assertTrue(HomeServiceStatus::PatientConfirmed->canTransitionTo(HomeServiceStatus::Scheduled));
        $this->assertTrue(HomeServiceStatus::Scheduled->canTransitionTo(HomeServiceStatus::Completed));
    }

    #[Test]
    public function it_rejects_shortcuts_and_backwards_transitions(): void
    {
        $this->assertFalse(HomeServiceStatus::Requested->canTransitionTo(HomeServiceStatus::Scheduled));
        $this->assertFalse(HomeServiceStatus::Requested->canTransitionTo(HomeServiceStatus::Completed));
        $this->assertFalse(HomeServiceStatus::Completed->canTransitionTo(HomeServiceStatus::Requested));
        $this->assertFalse(HomeServiceStatus::ProviderAccepted->canTransitionTo(HomeServiceStatus::Requested));
    }

    #[Test]
    public function terminal_states_are_terminal(): void
    {
        $this->assertTrue(HomeServiceStatus::Completed->isTerminal());
        $this->assertTrue(HomeServiceStatus::Rejected->isTerminal());
        $this->assertTrue(HomeServiceStatus::UnableToService->isTerminal());
        $this->assertTrue(HomeServiceStatus::Cancelled->isTerminal());
        $this->assertFalse(HomeServiceStatus::Scheduled->isTerminal());
    }

    #[Test]
    public function rejection_and_unable_to_service_are_reachable_from_early_states(): void
    {
        $this->assertTrue(HomeServiceStatus::Requested->canTransitionTo(HomeServiceStatus::Rejected));
        $this->assertTrue(HomeServiceStatus::Requested->canTransitionTo(HomeServiceStatus::UnableToService));
        $this->assertTrue(HomeServiceStatus::AreaVerified->canTransitionTo(HomeServiceStatus::UnableToService));
        $this->assertTrue(HomeServiceStatus::CoordinatorReview->canTransitionTo(HomeServiceStatus::UnableToService));
        $this->assertTrue(HomeServiceStatus::ProviderRequested->canTransitionTo(HomeServiceStatus::Rejected));
    }
}
