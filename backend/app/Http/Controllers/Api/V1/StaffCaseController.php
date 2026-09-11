<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Cases\Enums\CaseStatus;
use App\Domain\Cases\Services\CaseWorkflow;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Identity\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\ClinicalDocument;
use App\Models\PatientCase;
use App\Models\ReferralProposal;
use App\Models\ReviewRevision;
use App\Support\DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class StaffCaseController extends Controller
{
    public function assign(Request $request, PatientCase $case): JsonResponse
    {
        abort_unless($request->user()->role === UserRole::Coordinator && $request->user()->can('view', $case), 404);
        $data = $request->validate([
            'assignee_user_id' => ['required', 'exists:users,id'],
            'purpose' => ['required', 'in:coordination,clinical_review'],
            'version' => ['required', 'integer'],
        ]);

        return DB::transaction(function () use ($request, $case, $data): JsonResponse {
            $locked = PatientCase::query()->lockForUpdate()->findOrFail($case->id);

            if ($locked->version !== (int) $data['version']) {
                return response()->json(['error' => ['code' => 'case.version_conflict'], 'request_id' => $request->attributes->get('request_id')], 409);
            }

            if ($data['purpose'] === 'clinical_review') {
                $assignee = (int) $data['assignee_user_id'];
                $isClinician = DB::table('users')->where('id', $assignee)->value('role');
                if ($isClinician !== UserRole::Clinician->value) {
                    throw new DomainException(403, 'assignment.role_mismatch');
                }

                $active = DB::table('practitioners')
                    ->where('user_id', $assignee)
                    ->where('credential_status', 'verified')
                    ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                    ->exists();
                if (! $active) {
                    throw new DomainException(403, 'assignment.credential_invalid');
                }
            }

            DB::table('case_assignments')->updateOrInsert(
                ['case_id' => $case->id, 'assignee_user_id' => $data['assignee_user_id'], 'purpose' => $data['purpose'], 'released_at' => null],
                ['id' => (string) Str::ulid(), 'assigned_by_user_id' => $request->user()->id, 'assigned_at' => now(), 'created_at' => now(), 'updated_at' => now()]
            );

            $locked->increment('version');

            return response()->json(['data' => ['case_id' => $case->id, 'version' => $locked->version]])
                ->header('Cache-Control', 'private, no-store');
        });
    }

    public function status(Request $request, PatientCase $case, CaseWorkflow $workflow): JsonResponse
    {
        abort_unless($request->user()->can('updateStatus', $case), 404);
        $data = $request->validate([
            'status' => ['required', Rule::enum(CaseStatus::class)],
            'reason' => ['nullable', 'string', 'max:1000'],
            'version' => ['required', 'integer'],
        ]);

        try {
            $updated = $workflow->transition(
                $case,
                CaseStatus::from($data['status']),
                $request->user(),
                $data['reason'] ?? null,
                (int) $data['version']
            );
        } catch (HttpException $e) {
            return response()->json(['error' => ['code' => 'case.version_conflict'], 'request_id' => $request->attributes->get('request_id')], 409);
        } catch (\DomainException) {
            return response()->json(['error' => ['code' => 'case.invalid_transition'], 'request_id' => $request->attributes->get('request_id')], 409);
        }

        return response()->json(['data' => ['id' => $updated->id, 'status' => $updated->status->value, 'version' => $updated->version]])
            ->header('Cache-Control', 'private, no-store');
    }

    public function proposeReferral(Request $request, PatientCase $case): JsonResponse
    {
        abort_unless($request->user()->role === UserRole::Coordinator && $request->user()->can('view', $case), 404);
        $data = $request->validate([
            'clinic_id' => ['required', 'exists:clinics,id'],
            'reasoning' => ['required', 'string', 'max:2000'],
            'source_language' => ['required', 'in:fa,ar,en'],
        ]);

        $proposal = ReferralProposal::query()->create([
            'case_id' => $case->id,
            'clinic_id' => $data['clinic_id'],
            'proposed_by_user_id' => $request->user()->id,
            'status' => 'proposed',
            'reasoning' => $data['reasoning'],
            'source_language' => $data['source_language'],
            'proposed_at' => now(),
        ]);

        return response()->json(['data' => ['id' => $proposal->id, 'status' => $proposal->status]], 201)
            ->header('Cache-Control', 'private, no-store');
    }

    public function createReview(Request $request, PatientCase $case): JsonResponse
    {
        abort_unless($request->user()->role === UserRole::Clinician && $request->user()->can('view', $case), 404);

        $active = DB::table('practitioners')
            ->where('user_id', $request->user()->id)
            ->where('credential_status', 'verified')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->exists();
        abort_unless($active, 403);

        $data = $request->validate([
            'source_language' => ['required', 'in:fa,ar,en'],
            'clinical_document_id' => ['required', 'ulid', 'exists:clinical_documents,id'],
            'image_adequacy' => ['required', 'string', 'max:1000'],
            'observations' => ['required', 'string', 'max:5000'],
            'limitations' => ['required', 'string', 'max:3000'],
            'options' => ['required', 'string', 'max:5000'],
            'recommended_next_step' => ['required', 'string', 'max:3000'],
            'budget_band' => ['nullable', 'in:economic,balanced,flexible,call'],
        ]);

        $revision = DB::transaction(function () use ($request, $case, $data): ReviewRevision {
            // Serialise review allocation by locking the case row.
            PatientCase::query()->lockForUpdate()->findOrFail($case->id);

            // An OPG clinical review must be traceable to the exact approved
            // document being reviewed. The document must belong to the same case
            // and remain approved/authorised at creation time.
            $doc = ClinicalDocument::query()
                ->where('id', $data['clinical_document_id'])
                ->where('case_id', $case->id)
                ->where('status', DocumentStatus::Approved)
                ->lockForUpdate()
                ->first();
            if ($doc === null) {
                throw new DomainException(422, 'review.document_not_approved');
            }

            $number = ((int) ReviewRevision::query()->where('case_id', $case->id)->max('revision_number')) + 1;

            return ReviewRevision::query()->create([
                ...$data,
                'case_id' => $case->id,
                'clinician_user_id' => $request->user()->id,
                'revision_number' => $number,
            ]);
        });

        return response()->json(['data' => ['id' => $revision->id, 'revision_number' => $revision->revision_number]], 201)
            ->header('Cache-Control', 'private, no-store');
    }

    public function publishReview(Request $request, PatientCase $case, ReviewRevision $review): JsonResponse
    {
        abort_unless($review->case_id === $case->id && (int) $review->clinician_user_id === (int) $request->user()->id && $request->user()->role === UserRole::Clinician && $request->user()->can('view', $case), 404);

        return DB::transaction(function () use ($request, $case, $review): JsonResponse {
            // Row-lock the revision and revalidate state inside the transaction.
            $locked = ReviewRevision::query()->lockForUpdate()->findOrFail($review->id);
            abort_unless($locked->case_id === $case->id, 404);

            if ($locked->signed_at !== null) {
                return response()->json(['error' => ['code' => 'review.already_published'], 'request_id' => $request->attributes->get('request_id')], 409);
            }

            // Revalidate credential authority at publication time.
            $active = DB::table('practitioners')
                ->where('user_id', $request->user()->id)
                ->where('credential_status', 'verified')
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->exists();
            if (! $active) {
                throw new DomainException(403, 'review.credential_revoked');
            }

            $assigned = DB::table('case_assignments')
                ->where('case_id', $case->id)
                ->where('assignee_user_id', $request->user()->id)
                ->where('purpose', 'clinical_review')
                ->whereNull('released_at')
                ->exists();
            if (! $assigned) {
                throw new DomainException(403, 'review.assignment_released');
            }

            // Revalidate the source document relationship at publication time: the
            // linked OPG must still belong to this case and remain approved/authorised.
            $document = ClinicalDocument::query()
                ->where('id', $locked->clinical_document_id)
                ->where('case_id', $case->id)
                ->where('status', DocumentStatus::Approved)
                ->lockForUpdate()
                ->first();
            if ($document === null) {
                throw new DomainException(422, 'review.document_not_approved');
            }

            $locked->update(['signed_at' => now()]);

            DB::table('publication_events')->insert([
                'id' => (string) Str::ulid(),
                'review_revision_id' => $locked->id,
                'actor_user_id' => $request->user()->id,
                'event' => 'published',
                'created_at' => now(),
            ]);

            return response()->json(['data' => ['id' => $locked->id, 'published' => true]])
                ->header('Cache-Control', 'private, no-store');
        });
    }
}
