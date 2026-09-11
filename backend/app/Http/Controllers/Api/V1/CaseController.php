<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Cases\Enums\CaseStatus;
use App\Domain\Cases\Enums\ServiceType;
use App\Domain\Consent\Services\ConsentService;
use App\Domain\Coordination\Services\CoordinatorAssignment;
use App\Domain\Operations\Services\Idempotency;
use App\Domain\Operations\Services\Outbox;
use App\Http\Controllers\Controller;
use App\Models\PatientCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class CaseController extends Controller
{
    public function draft(Request $request, Idempotency $idempotency): JsonResponse
    {
        $data = $request->validate([
            'service_type' => ['required', Rule::enum(ServiceType::class)],
            'name' => ['nullable', 'string', 'max:80'],
            'tehran_area' => ['nullable', Rule::in(config('royadarman.tehran_areas'))],
            'preferred_contact_time' => ['nullable', 'in:any,morning,midday,evening,night'],
            'contact_reason' => ['nullable', 'string', 'max:1000'],
            'budget_band' => ['required', 'in:economic,balanced,flexible,call'],
            'budget_input_unit' => ['required', 'in:toman,irr'],
            'source_language' => ['required', 'in:fa,ar,en'],
        ]);

        if ($data['service_type'] === ServiceType::HomeDentistry->value && empty($data['tehran_area'])) {
            return response()->json([
                'error' => [
                    'code' => 'error.validation',
                    'message' => __('ui.errors.area_required'),
                    'details' => ['tehran_area' => [__('ui.errors.area_required')]],
                ],
                'request_id' => $request->attributes->get('request_id'),
            ], 422);
        }

        $result = $idempotency->execute($request->user(), 'case.draft', (string) $request->header('Idempotency-Key'), $data, function () use ($request, $data): array {
            $user = $request->user();
            $case = PatientCase::query()->create([
                'public_reference' => 'RD-'.strtoupper(Str::random(8)),
                'patient_user_id' => $user->id,
                'service_type' => $data['service_type'],
                'status' => CaseStatus::Draft,
                'priority' => 'normal',
                'patient_name' => $data['name'] ?? $user->name,
                'patient_mobile' => $user->phone,
                'patient_mobile_hash' => $user->phone_hash,
                'tehran_area' => $data['tehran_area'] ?? null,
                'preferred_contact_time' => $data['preferred_contact_time'] ?? null,
                'contact_reason' => $data['contact_reason'] ?? null,
                'budget_band' => $data['budget_band'],
                'source_language' => $data['source_language'],
                'budget_input_unit' => $data['budget_input_unit'],
                'currency' => 'IRR',
                'version' => 1,
            ]);

            return ['status' => 201, 'body' => ['data' => $this->resource($case)]];
        });

        return response()->json($result['body'], $result['status'])
            ->header('Cache-Control', 'private, no-store');
    }

    public function submit(
        Request $request,
        PatientCase $case,
        Idempotency $idempotency,
        Outbox $outbox,
        ConsentService $consent,
        CoordinatorAssignment $coordinatorAssignment,
    ): JsonResponse {
        abort_unless((int) $case->patient_user_id === (int) $request->user()->id, 404);
        $data = $request->validate([
            'version' => ['required', 'integer', 'min:1'],
            'policy_version' => ['required', 'string', 'max:50'],
            'content_hash' => ['required', 'string', 'size:64'],
        ]);

        $result = $idempotency->execute($request->user(), 'case.submit.'.$case->id, (string) $request->header('Idempotency-Key'), $data, function () use ($request, $case, $data, $outbox, $consent, $coordinatorAssignment): array {
            return DB::transaction(function () use ($request, $case, $data, $outbox, $consent, $coordinatorAssignment): array {
                $locked = PatientCase::query()->lockForUpdate()->findOrFail($case->id);
                if ($locked->status !== CaseStatus::Draft || $locked->version !== (int) $data['version']) {
                    abort(409, 'case.version_conflict');
                }

                $policy = $consent->resolvePolicy('case_coordination', $data['policy_version'], $locked->source_language);
                if (! $policy) {
                    return ['status' => 503, 'body' => ['error' => ['code' => 'error.consent.translation_unavailable'], 'request_id' => $request->attributes->get('request_id')]];
                }
                if (! hash_equals($policy->content_hash, $data['content_hash'])) {
                    return ['status' => 422, 'body' => ['error' => ['code' => 'consent.policy_mismatch'], 'request_id' => $request->attributes->get('request_id')]];
                }

                $consent->record($request->user(), 'case_coordination', $policy->id, $locked->source_language, $request, $locked->id);

                $locked->update([
                    'status' => CaseStatus::Submitted,
                    'version' => $locked->version + 1,
                    'submitted_at' => now(),
                ]);

                $coordinatorAssignment->assignInitial($locked);

                $outbox->record('case.submitted', PatientCase::class, $locked->id, [
                    'template_key' => 'case_submitted',
                    'reference' => $locked->public_reference,
                ], 'case.submitted.'.$locked->id, $request->user()->locale);

                return ['status' => 200, 'body' => ['data' => $this->resource($locked->refresh())]];
            });
        });

        return response()->json($result['body'], $result['status'])
            ->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, PatientCase $case): JsonResponse
    {
        abort_unless($request->user()->can('view', $case), 404);

        return response()->json(['data' => $this->resource($case)])
            ->header('Cache-Control', 'private, no-store');
    }

    private function resource(PatientCase $case): array
    {
        return [
            'id' => $case->id,
            'reference' => $case->public_reference,
            'service_type' => $case->service_type->value,
            'status' => $case->status->value,
            'version' => $case->version,
            'source_language' => $case->source_language,
            'submitted_at' => $case->submitted_at?->toIso8601String(),
        ];
    }
}
