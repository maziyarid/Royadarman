<?php

namespace App\Domain\Matching\Services;

use App\Domain\Matching\Contracts\MatchingService as MatchingServiceContract;
use App\Domain\Matching\Enums\MatchStrategy;
use App\Domain\Matching\Enums\UrgencyLevel;
use App\Models\Clinic;
use App\Models\ClinicBranch;
use App\Models\MatchCandidate;
use App\Models\MatchRun;
use App\Models\ReferralRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class MatchingService implements MatchingServiceContract
{
    private const DEFAULT_RADIUS_KM = 50;
    private const MAX_CANDIDATES = 20;

    // Weight configuration for hybrid scoring
    private const SCORE_WEIGHTS = [
        'travel_time' => 0.35,
        'time_to_slot' => 0.30,
        'service_fit' => 0.15,
        'acceptance_reliability' => 0.10,
        'fair_distribution' => 0.10,
    ];

    public function runMatching(ReferralRequest $request, string $strategy = null): MatchRun
    {
        $strategy = $strategy ?? MatchStrategy::default()->value;

        return DB::transaction(function () use ($request, $strategy): MatchRun {
            $matchRun = MatchRun::query()->create([
                'id' => (string) Str::ulid(),
                'referral_request_id' => $request->id,
                'initiated_by_user_id' => $request->patient_user_id,
                'strategy' => $strategy,
                'criteria' => $this->buildCriteria($request),
                'status' => 'running',
                'started_at' => now(),
            ]);

            $candidates = $this->findCandidates($request);
            $scoredCandidates = $this->scoreCandidates($request, $candidates);

            $this->storeCandidates($matchRun, $scoredCandidates);

            $matchRun->update([
                'candidate_count' => count($candidates),
                'result_count' => count($scoredCandidates),
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            return $matchRun;
        });
    }

    public function getCandidates(MatchRun $matchRun): array
    {
        return MatchCandidate::query()
            ->with(['clinicBranch.clinic', 'clinic'])
            ->where('match_run_id', $matchRun->id)
            ->orderBy('rank')
            ->orderBy('score', 'desc')
            ->get()
            ->toArray();
    }

    public function scoreCandidates(ReferralRequest $request, array $candidates): array
    {
        $scored = [];
        $rank = 1;

        foreach ($candidates as $candidate) {
            $score = $this->calculateScore($request, $candidate);
            $scored[] = [
                ...$candidate,
                'score' => $score,
                'rank' => $rank++,
                'score_breakdown' => $this->calculateScoreBreakdown($request, $candidate),
            ];
        }

        // Sort by score descending
        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $scored;
    }

    public function isEligible(string $clinicId, array $criteria): bool
    {
        $clinic = Clinic::query()->find($clinicId);

        if (! $clinic || ! $clinic->is_active) {
            return false;
        }

        // Check service type eligibility
        if (isset($criteria['service_type'])) {
            $hasService = ClinicBranch::query()
                ->where('clinic_id', $clinicId)
                ->whereHas('branchServices.service', fn ($q) => $q->where('category', $criteria['service_type']))
                ->exists();

            if (! $hasService) {
                return false;
            }
        }

        // Check budget eligibility
        if (isset($criteria['budget_band'])) {
            $clinicPrices = ClinicBranch::query()
                ->where('clinic_id', $clinicId)
                ->whereHas('branchServices', fn ($q) => $q->whereNotNull('base_price'))
                ->with('branchServices')
                ->get()
                ->flatMap(fn ($branch) => $branch->branchServices->pluck('base_price'))
                ->filter()
                ->toArray();

            if (empty($clinicPrices)) {
                return false;
            }

            $minPrice = min($clinicPrices);
            $maxPrice = max($clinicPrices);

            // Simple budget check - can be enhanced
            if ($criteria['budget_band'] === 'economic' && $minPrice > config('royadarman.budget_thresholds.economic', 1000000)) {
                return false;
            }
        }

        return true;
    }

    public function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        // Haversine formula
        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    public function getTravelTime(float $lat1, float $lon1, float $lat2, float $lon2): int
    {
        $distance = $this->calculateDistance($lat1, $lon1, $lat2, $lon2);

        // Simple estimate: 30 km/h average speed in urban areas
        $speedKmh = 30;
        $hours = $distance / $speedKmh;

        return (int) ceil($hours * 60);
    }

    /**
     * Build matching criteria from referral request
     */
    private function buildCriteria(ReferralRequest $request): array
    {
        return [
            'service_type' => $request->service_type,
            'budget_band' => $request->budget_band,
            'budget_amount' => $request->budget_amount,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'tehran_area' => $request->tehran_area,
            'preferred_gender' => $request->preferred_gender,
            'preferred_language' => $request->preferred_language,
            'urgency' => $request->urgencyAssessments()->latest()?->level ?? UrgencyLevel::Normal->value,
        ];
    }

    /**
     * Find potential clinic candidates
     */
    private function findCandidates(ReferralRequest $request): array
    {
        if (! $request->latitude || ! $request->longitude) {
            // Fallback to all active clinics if no location
            return ClinicBranch::query()
                ->with(['clinic'])
                ->where('is_active', true)
                ->whereHas('clinic', fn ($q) => $q->where('is_active', true))
                ->limit(self::MAX_CANDIDATES * 2)
                ->get()
                ->map(fn ($branch) => [
                    'clinic_branch_id' => $branch->id,
                    'clinic_id' => $branch->clinic_id,
                    'distance_km' => null,
                    'travel_time_minutes' => null,
                ])
                ->toArray();
        }

        // Find clinics within radius
        $candidates = ClinicBranch::query()
            ->with(['clinic'])
            ->where('is_active', true)
            ->whereHas('clinic', fn ($q) => $q->where('is_active', true))
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get()
            ->map(function ($branch) use ($request) {
                $distance = $this->calculateDistance(
                    (float) $request->latitude,
                    (float) $request->longitude,
                    (float) $branch->latitude,
                    (float) $branch->longitude
                );

                if ($distance > self::DEFAULT_RADIUS_KM) {
                    return null;
                }

                $travelTime = $this->getTravelTime(
                    (float) $request->latitude,
                    (float) $request->longitude,
                    (float) $branch->latitude,
                    (float) $branch->longitude
                );

                return [
                    'clinic_branch_id' => $branch->id,
                    'clinic_id' => $branch->clinic_id,
                    'distance_km' => $distance,
                    'travel_time_minutes' => $travelTime,
                ];
            })
            ->filter()
            ->sortBy('distance_km')
            ->take(self::MAX_CANDIDATES)
            ->values()
            ->toArray();

        return $candidates;
    }

    /**
     * Store match candidates in database
     */
    private function storeCandidates(MatchRun $matchRun, array $scoredCandidates): void
    {
        $now = now();
        $expiresAt = $now->addMinutes(config('royadarman.match_expiry_minutes', 30));

        foreach ($scoredCandidates as $candidate) {
            MatchCandidate::query()->create([
                'id' => (string) Str::ulid(),
                'match_run_id' => $matchRun->id,
                'clinic_branch_id' => $candidate['clinic_branch_id'],
                'clinic_id' => $candidate['clinic_id'],
                'distance_km' => $candidate['distance_km'],
                'travel_time_minutes' => $candidate['travel_time_minutes'],
                'score' => $candidate['score'],
                'rank' => $candidate['rank'],
                'score_breakdown' => $candidate['score_breakdown'],
                'status' => 'candidate',
                'is_available' => true,
                'available_at' => $now,
                'expires_at' => $expiresAt,
            ]);
        }
    }

    /**
     * Calculate overall score for a candidate
     */
    private function calculateScore(ReferralRequest $request, array $candidate): int
    {
        $breakdown = $this->calculateScoreBreakdown($request, $candidate);

        return (int) round(array_sum($breakdown));
    }

    /**
     * Calculate score breakdown for a candidate
     */
    private function calculateScoreBreakdown(ReferralRequest $request, array $candidate): array
    {
        $breakdown = [];
        $clinicBranch = ClinicBranch::query()->find($candidate['clinic_branch_id']);

        if (! $clinicBranch) {
            return $breakdown;
        }

        // Travel time score (0-100)
        $travelTime = $candidate['travel_time_minutes'] ?? 60;
        $travelScore = max(0, 100 - min(100, $travelTime * 2));
        $breakdown['travel_time'] = (int) ($travelScore * self::SCORE_WEIGHTS['travel_time']);

        // Time to slot score (0-100)
        $timeToSlot = $this->calculateTimeToSlot($clinicBranch);
        $timeToSlotScore = max(0, 100 - min(100, $timeToSlot / 60 * 20));
        $breakdown['time_to_slot'] = (int) ($timeToSlotScore * self::SCORE_WEIGHTS['time_to_slot']);

        // Service fit score (0-100)
        $serviceFit = $this->calculateServiceFit($clinicBranch, $request->service_type);
        $breakdown['service_fit'] = (int) ($serviceFit * self::SCORE_WEIGHTS['service_fit']);

        // Acceptance reliability score (0-100)
        $reliability = $this->calculateReliability($clinicBranch->clinic_id);
        $breakdown['acceptance_reliability'] = (int) ($reliability * self::SCORE_WEIGHTS['acceptance_reliability']);

        // Fair distribution score (0-100)
        $fairness = $this->calculateFairness($clinicBranch->clinic_id);
        $breakdown['fair_distribution'] = (int) ($fairness * self::SCORE_WEIGHTS['fair_distribution']);

        return $breakdown;
    }

    /**
     * Calculate time to next available slot
     */
    private function calculateTimeToSlot(ClinicBranch $branch): int
    {
        // Check for instant slots
        $nextSlot = $branch->appointmentSlots()
            ->where('status', 'available')
            ->where('date', '>=', now()->toDateString())
            ->orderBy('date')
            ->orderBy('start_time')
            ->first();

        if ($nextSlot) {
            $slotDateTime = $nextSlot->date->format('Y-m-d') . ' ' . $nextSlot->start_time->format('H:i:s');
            $diff = strtotime($slotDateTime) - time();
            return max(0, (int) ceil($diff / 60));
        }

        // Check capacity windows
        $nextWindow = $branch->capacityWindows()
            ->where('status', 'open')
            ->where('date', '>=', now()->toDateString())
            ->orderBy('date')
            ->orderBy('start_time')
            ->first();

        if ($nextWindow) {
            $windowDateTime = $nextWindow->date->format('Y-m-d') . ' ' . $nextWindow->start_time->format('H:i:s');
            $diff = strtotime($windowDateTime) - time();
            return max(0, (int) ceil($diff / 60));
        }

        // Default to 24 hours if no availability found
        return 1440;
    }

    /**
     * Calculate service fit score
     */
    private function calculateServiceFit(ClinicBranch $branch, string $serviceType): float
    {
        if (! $serviceType) {
            return 100.0;
        }

        $hasService = $branch->branchServices()
            ->whereHas('service', fn ($q) => $q->where('category', $serviceType))
            ->exists();

        return $hasService ? 100.0 : 0.0;
    }

    /**
     * Calculate acceptance reliability score
     */
    private function calculateReliability(string $clinicId): float
    {
        // Placeholder - implement based on historical acceptance rates
        return 80.0;
    }

    /**
     * Calculate fairness score (distribution)
     */
    private function calculateFairness(string $clinicId): float
    {
        // Placeholder - implement based on recent assignment history
        return 90.0;
    }
}
