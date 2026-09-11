<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Consent\Services\ConsentService;
use App\Domain\Operations\Services\Outbox;
use App\Http\Controllers\Controller;
use App\Models\PatientCase;
use App\Models\ReferralProposal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ReferralController extends Controller
{
    public function decide(Request $request, PatientCase $case, ReferralProposal $proposal, Outbox $outbox, ConsentService $consent): JsonResponse
    {
        abort_unless($proposal->case_id === $case->id && (int) $case->patient_user_id === (int) $request->user()->id, 404);
        $data = $request->validate([
            'decision' => ['required', 'in:accepted,declined'],
            'policy_version' => ['nullable', 'required_if:decision,accepted', 'string', 'max:50'],
            'content_hash' => ['nullable', 'required_if:decision,accepted', 'string', 'max:64'],
        ]);

        return DB::transaction(function () use ($request, $case, $proposal, $data, $outbox, $consent): JsonResponse {
            $locked = ReferralProposal::query()->lockForUpdate()->findOrFail($proposal->id);
            if ($locked->status !== 'proposed' || $locked->withdrawn_at) {
                return response()->json(['error' => ['code' => 'referral.not_available'], 'request_id' => $request->attributes->get('request_id')], 422);
            }
            $locked->update(['status' => $data['decision'], 'decided_at' => now()]);
            if ($data['decision'] === 'accepted') {
                // Bind acceptance to the EXACT published policy version/hash the
                // patient was shown; do not infer consent from latestPublishedPolicy().
                $policy = $consent->resolvePolicy('referral_sharing', $data['policy_version'], $locked->source_language);
                if ($policy === null || ! hash_equals($policy->content_hash, $data['content_hash'])) {
                    return response()->json(['error' => ['code' => 'consent.policy_mismatch'], 'request_id' => $request->attributes->get('request_id')], 422);
                }

                $ttl = config('royadarman.referral.grant_ttl_minutes');
                if (! is_numeric($ttl) || (int) $ttl <= 0) {
                    return response()->json(['error' => ['code' => 'referral.grant_ttl_unconfigured'], 'request_id' => $request->attributes->get('request_id')], 503);
                }

                $consentEvent = $consent->record(
                    $request->user(),
                    'referral_sharing',
                    $policy->id,
                    $locked->source_language,
                    $request,
                    $case->id,
                );
                DB::table('referral_grants')->insert([
                    'id' => (string) Str::ulid(),
                    'proposal_id' => $locked->id,
                    'consent_event_id' => $consentEvent->id,
                    'case_id' => $case->id,
                    'clinic_id' => $locked->clinic_id,
                    'scope' => json_encode(['contact', 'service_need'], JSON_THROW_ON_ERROR),
                    'granted_at' => now(),
                    'expires_at' => now()->addMinutes((int) $ttl),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $outbox->record('referral.accepted', ReferralProposal::class, $locked->id, ['template_key' => 'referral_accepted'], 'referral.accepted.'.$locked->id, $request->user()->locale);
            }

            return response()->json(['data' => ['id' => $locked->id, 'status' => $locked->status]]);
        });
    }
}
