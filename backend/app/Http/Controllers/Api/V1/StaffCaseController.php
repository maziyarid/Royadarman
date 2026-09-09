<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Cases\Enums\CaseStatus;
use App\Domain\Cases\Services\CaseWorkflow;
use App\Domain\Identity\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\PatientCase;
use App\Models\ReferralProposal;
use App\Models\ReviewRevision;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class StaffCaseController extends Controller
{
    public function assign(Request $request, PatientCase $case): JsonResponse
    {
        abort_unless(in_array($request->user()->role, [UserRole::Coordinator, UserRole::TechnicalAdministrator], true), 403);
        $data = $request->validate(['assignee_user_id' => ['required', 'exists:users,id'], 'purpose' => ['required', 'in:coordination,clinical_review'], 'version' => ['required', 'integer']]);

        return DB::transaction(function () use ($request, $case, $data): JsonResponse {
            $locked = PatientCase::query()->lockForUpdate()->findOrFail($case->id);
            if ($locked->version !== (int) $data['version']) {
                return response()->json(['error' => ['code' => 'case.version_conflict']], 409);
            }
            DB::table('case_assignments')->updateOrInsert(['case_id' => $case->id, 'assignee_user_id' => $data['assignee_user_id'], 'purpose' => $data['purpose'], 'released_at' => null], ['id' => (string) Str::ulid(), 'assigned_by_user_id' => $request->user()->id, 'assigned_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            $locked->increment('version');

            return response()->json(['data' => ['case_id' => $case->id, 'version' => $locked->version]]);
        });
    }

    public function status(Request $request, PatientCase $case, CaseWorkflow $workflow): JsonResponse
    {
        abort_unless($request->user()->can('updateStatus', $case), 404);
        $data = $request->validate(['status' => ['required', Rule::enum(CaseStatus::class)], 'reason' => ['nullable', 'string', 'max:1000'], 'version' => ['required', 'integer']]);
        if ($case->version !== (int) $data['version']) {
            return response()->json(['error' => ['code' => 'case.version_conflict']], 409);
        }
        try {
            $updated = $workflow->transition($case, CaseStatus::from($data['status']), $request->user(), $data['reason'] ?? null);
        } catch (\DomainException) {
            return response()->json(['error' => ['code' => 'case.invalid_transition']], 409);
        }

        return response()->json(['data' => ['id' => $updated->id, 'status' => $updated->status->value, 'version' => $updated->version]]);
    }

    public function proposeReferral(Request $request, PatientCase $case): JsonResponse
    {
        abort_unless($request->user()->role === UserRole::Coordinator && $request->user()->can('view', $case), 404);
        $data = $request->validate(['clinic_id' => ['required', 'exists:clinics,id'], 'reasoning' => ['required', 'string', 'max:2000'], 'source_language' => ['required', 'in:fa,ar,en']]);
        $proposal = ReferralProposal::query()->create(['case_id' => $case->id, 'clinic_id' => $data['clinic_id'], 'proposed_by_user_id' => $request->user()->id, 'status' => 'proposed', 'reasoning' => $data['reasoning'], 'source_language' => $data['source_language'], 'proposed_at' => now()]);

        return response()->json(['data' => ['id' => $proposal->id, 'status' => $proposal->status]], 201);
    }

    public function createReview(Request $request, PatientCase $case): JsonResponse
    {
        abort_unless($request->user()->role === UserRole::Clinician && $request->user()->can('view', $case), 404);
        $active = DB::table('practitioners')->where('user_id', $request->user()->id)->where('credential_status', 'verified')->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->exists();
        abort_unless($active, 403);
        $data = $request->validate(['source_language' => ['required', 'in:fa,ar,en'], 'image_adequacy' => ['required', 'string', 'max:1000'], 'observations' => ['required', 'string', 'max:5000'], 'limitations' => ['required', 'string', 'max:3000'], 'options' => ['required', 'string', 'max:5000'], 'recommended_next_step' => ['required', 'string', 'max:3000'], 'budget_band' => ['nullable', 'in:economic,balanced,flexible,call']]);
        $revision = DB::transaction(function () use ($request, $case, $data): ReviewRevision {
            $number = ((int) ReviewRevision::query()->where('case_id', $case->id)->max('revision_number')) + 1;

            return ReviewRevision::query()->create([...$data, 'case_id' => $case->id, 'clinician_user_id' => $request->user()->id, 'revision_number' => $number]);
        });

        return response()->json(['data' => ['id' => $revision->id, 'revision_number' => $revision->revision_number]], 201);
    }

    public function publishReview(Request $request, PatientCase $case, ReviewRevision $review): JsonResponse
    {
        abort_unless($review->case_id === $case->id && (int) $review->clinician_user_id === (int) $request->user()->id && $request->user()->role === UserRole::Clinician && $request->user()->can('view', $case), 404);
        abort_if($review->signed_at, 409);
        DB::transaction(function () use ($request, $review): void {
            $review->update(['signed_at' => now()]);
            DB::table('publication_events')->insert(['id' => (string) Str::ulid(), 'review_revision_id' => $review->id, 'actor_user_id' => $request->user()->id, 'event' => 'published', 'created_at' => now()]);
        });

        return response()->json(['data' => ['id' => $review->id, 'published' => true]]);
    }
}

