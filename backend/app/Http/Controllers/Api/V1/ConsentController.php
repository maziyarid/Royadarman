<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Consent\Services\ConsentService;
use App\Http\Controllers\Controller;
use App\Models\PatientCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ConsentController extends Controller
{
    public function __construct(private readonly ConsentService $consent) {}

    public function accept(Request $request, PatientCase $case, string $purpose): JsonResponse
    {
        abort_unless((int) $case->patient_user_id === (int) $request->user()->id, 404);

        $supported = ['opg_document_sharing', 'referral_sharing'];
        abort_unless(in_array($purpose, $supported, true), 404);

        $data = $request->validate([
            'policy_version' => ['required', 'string', 'max:50'],
            'content_hash' => ['required', 'string', 'max:64'],
            'locale' => ['required', 'in:fa,ar,en'],
        ]);

        $policy = $this->consent->resolvePolicy($purpose, $data['policy_version'], $data['locale']);
        if ($policy === null) {
            return response()->json(['error' => ['code' => 'error.consent.translation_unavailable'], 'request_id' => $request->attributes->get('request_id')], 503);
        }

        // Bind the patient to the exact policy version/hash they were shown, so a
        // silently substituted newer policy cannot be accepted.
        if (! hash_equals($policy->content_hash, $data['content_hash'])) {
            return response()->json(['error' => ['code' => 'consent.policy_mismatch'], 'request_id' => $request->attributes->get('request_id')], 422);
        }

        $event = $this->consent->record(
            $request->user(),
            $purpose,
            $policy->id,
            $data['locale'],
            $request,
            $case->id,
        );

        return response()->json([
            'data' => [
                'id' => $event->id,
                'purpose' => $purpose,
                'policy_version' => $policy->version,
                'content_hash' => $policy->content_hash,
                'decision' => 'accepted',
            ],
        ], 201)->header('Cache-Control', 'private, no-store');
    }

    public function revoke(Request $request, PatientCase $case, string $purpose): JsonResponse
    {
        abort_unless((int) $case->patient_user_id === (int) $request->user()->id, 404);

        $supported = ['opg_document_sharing', 'referral_sharing'];
        abort_unless(in_array($purpose, $supported, true), 404);

        $event = $this->consent->latestActiveFor($request->user(), $purpose, $case->id, $purpose);
        if ($event === null) {
            return response()->json(['error' => ['code' => 'consent.no_active_consent'], 'request_id' => $request->attributes->get('request_id')], 404);
        }

        $this->consent->revoke($event);

        return response()->json(['data' => ['id' => $event->id, 'revoked' => true]])
            ->header('Cache-Control', 'private, no-store');
    }
}
