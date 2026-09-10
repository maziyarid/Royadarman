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
        $data = $request->validate(['decision' => ['required', 'in:accepted,declined']]);

        return DB::transaction(function () use ($request, $case, $proposal, $data, $outbox, $consent): JsonResponse {
            $locked = ReferralProposal::query()->lockForUpdate()->findOrFail($proposal->id);
            if ($locked->status !== 'proposed' || $locked->withdrawn_at) {
                return response()->json(['error' => ['code' => 'referral.not_available']], 422);
            }
            $locked->update(['status' => $data['decision'], 'decided_at' => now()]);
            if ($data['decision'] === 'accepted') {
                $policy = $consent->latestPublishedPolicy('referral_sharing', $locked->source_language);
                abort_unless($policy !== null, 503, 'error.consent.translation_unavailable');
                $consentEvent = $consent->record(
                    $request->user(),
                    'referral_sharing',
                    $policy->id,
                    $locked->source_language,
                    $request,
                    $case->id,
                );
                DB::table('referral_grants')->insert(['id' => (string) Str::ulid(), 'proposal_id' => $locked->id, 'consent_event_id' => $consentEvent->id, 'case_id' => $case->id, 'clinic_id' => $locked->clinic_id, 'scope' => json_encode(['contact', 'service_need'], JSON_THROW_ON_ERROR), 'granted_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
                $outbox->record('referral.accepted', ReferralProposal::class, $locked->id, ['template_key' => 'referral_accepted'], 'referral.accepted.'.$locked->id, $request->user()->locale);
            }

            return response()->json(['data' => ['id' => $locked->id, 'status' => $locked->status]]);
        });
    }
}
