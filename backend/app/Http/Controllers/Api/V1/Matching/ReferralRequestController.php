<?php

namespace App\Http\Controllers\Api\V1\Matching;

use App\Domain\Matching\Enums\BudgetBand;
use App\Domain\Matching\Enums\MatchStrategy;
use App\Domain\Matching\Services\MatchingService;
use App\Domain\Operations\Services\Idempotency;
use App\Domain\Operations\Services\Outbox;
use App\Http\Controllers\Controller;
use App\Models\ReferralRequest;
use App\Support\DigitNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class ReferralRequestController extends Controller
{
    public function __construct(
        private readonly MatchingService $matchingService,
        private readonly Idempotency $idempotency,
        private readonly Outbox $outbox
    ) {}

    public function draft(Request $request, Idempotency $idempotency): JsonResponse
    {
        $data = $request->validate([
            'service_type' => ['required', 'string', 'max:40'],
            'patient_name' => ['nullable', 'string', 'max:80'],
            'patient_mobile' => ['required', 'string', 'max:32'],
            'latitude' => ['nullable', 'numeric', 'min:-90', 'max:90'],
            'longitude' => ['nullable', 'numeric', 'min:-180', 'max:180'],
            'tehran_area' => ['nullable', 'string', 'max:80'],
            'preferred_contact_time' => ['nullable', 'in:any,morning,midday,evening,night'],
            'contact_reason' => ['nullable', 'string', 'max:1000'],
            'budget_band' => ['required', Rule::enum(BudgetBand::class)],
            'budget_amount' => ['nullable', 'integer', 'min:0'],
            'budget_currency' => ['string', 'size:3'],
            'preferred_gender' => ['nullable', 'in:male,female,any'],
            'preferred_language' => ['nullable', 'in:fa,ar,en'],
            'preferences' => ['nullable', 'array'],
            'source_language' => ['required', 'in:fa,ar,en'],
        ]);

        $result = $idempotency->execute(
            $request->user(),
            'referral_request.draft',
            (string) $request->header('Idempotency-Key'),
            $data,
            function () use ($request, $data): array {
                $mobile = DigitNormalizer::iranianMobile($data['patient_mobile']);
                $phoneHash = hash_hmac('sha256', $mobile, (string) config('royadarman.phone_hash_key'));

                $referralRequest = ReferralRequest::query()->create([
                    'id' => (string) Str::ulid(),
                    'patient_user_id' => $request->user()->id,
                    'public_reference' => 'RR-' . strtoupper(Str::random(8)),
                    'status' => 'draft',
                    'service_type' => $data['service_type'],
                    'priority' => 'normal',
                    'patient_name' => $data['patient_name'] ?? $request->user()->name,
                    'patient_mobile' => $mobile,
                    'patient_mobile_hash' => $phoneHash,
                    'latitude' => $data['latitude'],
                    'longitude' => $data['longitude'],
                    'tehran_area' => $data['tehran_area'],
                    'preferred_contact_time' => $data['preferred_contact_time'],
                    'contact_reason' => $data['contact_reason'],
                    'budget_band' => $data['budget_band'],
                    'budget_amount' => $data['budget_amount'],
                    'budget_currency' => $data['budget_currency'] ?? 'IRR',
                    'preferred_gender' => $data['preferred_gender'],
                    'preferred_language' => $data['preferred_language'],
                    'preferences' => $data['preferences'] ?? [],
                    'source_language' => $data['source_language'],
                    'submitted_at' => null,
                    'expires_at' => now()->addHours(24),
                ]);

                return [
                    'status' => 201,
                    'body' => ['data' => $this->resource($referralRequest)],
                ];
            }
        );

        return response()->json($result['body'], $result['status']);
    }

    public function submit(Request $request, ReferralRequest $referralRequest, Idempotency $idempotency, MatchingService $matchingService, Outbox $outbox): JsonResponse
    {
        abort_unless((int) $referralRequest->patient_user_id === (int) $request->user()->id, 404);

        $data = $request->validate([
            'version' => ['required', 'integer', 'min:1'],
            'policy_version' => ['required', 'string', 'max:50'],
        ]);

        $result = $idempotency->execute(
            $request->user(),
            'referral_request.submit.' . $referralRequest->id,
            (string) $request->header('Idempotency-Key'),
            $data,
            function () use ($request, $referralRequest, $data, $matchingService, $outbox): array {
                return DB::transaction(function () use ($request, $referralRequest, $data, $matchingService, $outbox): array {
                    $locked = ReferralRequest::query()
                        ->lockForUpdate()
                        ->findOrFail($referralRequest->id);

                    if ($locked->status !== 'draft' || $locked->version !== (int) $data['version']) {
                        abort(409);
                    }

                    // Submit the request
                    $locked->update([
                        'status' => 'submitted',
                        'version' => $locked->version + 1,
                        'submitted_at' => now(),
                    ]);

                    // Run matching
                    $matchRun = $matchingService->runMatching($locked);

                    // Send notification
                    $outbox->record(
                        'referral_request.submitted',
                        ReferralRequest::class,
                        $locked->id,
                        [
                            'template_key' => 'referral_request_submitted',
                            'reference' => $locked->public_reference,
                        ],
                        'referral_request.submitted.' . $locked->id,
                        $request->user()->locale
                    );

                    return [
                        'status' => 200,
                        'body' => [
                            'data' => [
                                'referral_request' => $this->resource($locked->refresh()),
                                'match_run' => [
                                    'id' => $matchRun->id,
                                    'status' => $matchRun->status,
                                    'candidate_count' => $matchRun->candidate_count,
                                    'result_count' => $matchRun->result_count,
                                ],
                            ],
                        ],
                    ];
                });
            }
        );

        return response()->json($result['body'], $result['status']);
    }

    public function show(Request $request, ReferralRequest $referralRequest): JsonResponse
    {
        abort_unless(
            $request->user()->can('view', $referralRequest) ||
            $referralRequest->patient_user_id === $request->user()->id,
            404
        );

        $referralRequest->load([
            'patient',
            'intakeAnswers',
            'urgencyAssessments',
            'patientPreferences',
            'matchRuns.candidates.clinicBranch.clinic',
            'matchDecisions',
            'slotHolds',
            'appointments',
        ]);

        return response()->json(['data' => $this->resource($referralRequest)]);
    }

    public function getCandidates(Request $request, ReferralRequest $referralRequest): JsonResponse
    {
        abort_unless(
            $request->user()->can('view', $referralRequest) ||
            $referralRequest->patient_user_id === $request->user()->id,
            404
        );

        $matchRun = $referralRequest->matchRuns()->latest()->firstOrFail();
        $candidates = $this->matchingService->getCandidates($matchRun);

        return response()->json(['data' => ['candidates' => $candidates]]);
    }

    public function decideCandidate(Request $request, ReferralRequest $referralRequest): JsonResponse
    {
        abort_unless($referralRequest->patient_user_id === $request->user()->id, 404);

        $data = $request->validate([
            'match_candidate_id' => ['required', 'string'],
            'decision' => ['required', 'in:accepted,rejected,skipped'],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        return DB::transaction(function () use ($request, $referralRequest, $data): JsonResponse {
            $matchCandidate = $referralRequest->matchCandidates()
                ->where('id', $data['match_candidate_id'])
                ->firstOrFail();

            // Check if already decided
            $existingDecision = $referralRequest->matchDecisions()
                ->where('match_candidate_id', $matchCandidate->id)
                ->first();

            if ($existingDecision) {
                abort(409, 'Already decided for this candidate');
            }

            // Create decision
            $decision = $referralRequest->matchDecisions()->create([
                'id' => (string) Str::ulid(),
                'match_candidate_id' => $matchCandidate->id,
                'decided_by_user_id' => $request->user()->id,
                'decision' => $data['decision'],
                'reason' => $data['reason'],
                'decided_at' => now(),
            ]);

            // If accepted, update candidate status
            if ($data['decision'] === 'accepted') {
                $matchCandidate->update(['status' => 'selected']);

                // Create a slot hold if this is instant booking
                if ($matchCandidate->clinicBranch->bookingModes()->where('mode', 'instant')->exists()) {
                    $this->createSlotHold($referralRequest, $matchCandidate);
                }
            }

            return response()->json(['data' => [
                'decision' => $decision,
                'match_candidate' => $matchCandidate->refresh(),
            ]]);
        });
    }

    private function resource(ReferralRequest $referralRequest): array
    {
        return [
            'id' => $referralRequest->id,
            'public_reference' => $referralRequest->public_reference,
            'status' => $referralRequest->status,
            'service_type' => $referralRequest->service_type,
            'priority' => $referralRequest->priority,
            'patient_name' => $referralRequest->patient_name,
            'tehran_area' => $referralRequest->tehran_area,
            'preferred_contact_time' => $referralRequest->preferred_contact_time,
            'contact_reason' => $referralRequest->contact_reason,
            'budget_band' => $referralRequest->budget_band,
            'budget_amount' => $referralRequest->budget_amount,
            'budget_currency' => $referralRequest->budget_currency,
            'preferred_gender' => $referralRequest->preferred_gender,
            'preferred_language' => $referralRequest->preferred_language,
            'preferences' => $referralRequest->preferences,
            'source_language' => $referralRequest->source_language,
            'latitude' => $referralRequest->latitude,
            'longitude' => $referralRequest->longitude,
            'submitted_at' => $referralRequest->submitted_at?->toIso8601String(),
            'expires_at' => $referralRequest->expires_at?->toIso8601String(),
            'matched_at' => $referralRequest->matched_at?->toIso8601String(),
            'closed_at' => $referralRequest->closed_at?->toIso8601String(),
            'created_at' => $referralRequest->created_at->toIso8601String(),
            'updated_at' => $referralRequest->updated_at->toIso8601String(),
        ];
    }

    private function createSlotHold(ReferralRequest $referralRequest, $matchCandidate): void
    {
        // Find first available slot at this clinic branch
        $slot = $matchCandidate->clinicBranch->appointmentSlots()
            ->where('status', 'available')
            ->where('date', '>=', now()->toDateString())
            ->orderBy('date')
            ->orderBy('start_time')
            ->first();

        if ($slot) {
            $holdToken = (string) Str::ulid();

            $slot->slotHolds()->create([
                'id' => (string) Str::ulid(),
                'referral_request_id' => $referralRequest->id,
                'patient_user_id' => $referralRequest->patient_user_id,
                'token' => $holdToken,
                'status' => 'active',
                'expires_at' => now()->addMinutes(10),
                'created_at' => now(),
                'ip_hash' => null,
                'user_agent_hash' => null,
                'metadata' => json_encode(['match_candidate_id' => $matchCandidate->id]),
            ]);

            $slot->update(['status' => 'held']);
        }
    }
}
