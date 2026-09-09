<?php

namespace App\Domain\Matching\Contracts;

use App\Models\ReferralRequest;
use App\Models\MatchRun;

interface MatchingService
{
    /**
     * Run matching for a referral request
     */
    public function runMatching(ReferralRequest $request, string $strategy = null): MatchRun;

    /**
     * Get candidates for a match run
     */
    public function getCandidates(MatchRun $matchRun): array;

    /**
     * Score and rank candidates
     */
    public function scoreCandidates(ReferralRequest $request, array $candidates): array;

    /**
     * Check if clinic is eligible for matching
     */
    public function isEligible(string $clinicId, array $criteria): bool;

    /**
     * Calculate distance between two points (in km)
     */
    public function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float;

    /**
     * Get travel time estimate (in minutes)
     */
    public function getTravelTime(float $lat1, float $lon1, float $lat2, float $lon2): int;
}
