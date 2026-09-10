<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Cases\Enums\CaseStatus;
use App\Domain\Cases\Enums\ServiceType;
use App\Domain\Operations\Services\Idempotency;
use App\Domain\Operations\Services\Outbox;
use App\Http\Controllers\Controller;
use App\Models\PatientCase;
use App\Models\PolicyVersion;
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
            'service_type' => ['required', Rule::enum(ServiceType::class)], 'name' => ['nullable', 'string', 'max:80'],
            'tehran_area' => ['nullable', Rule::in(config('royadarman.tehran_areas'))], 'preferred_contact_time' => ['nullable', 'in:any,morning,midday,evening,night'],
            'contact_reason' => ['nullable', 'string', 'max:1000'], 'budget_band' => ['required', 'in:economic,balanced,flexible,call'],
            'budget_input_unit' => ['required', 'in:toman,irr'], 'source_language' => ['required', 'in:fa,ar,en'],
        ]);
        if ($data['service_type'] === ServiceType::HomeDentistry->value && empty($data['tehran_area'])) {
            return response()->json(['errors' => ['tehran_area' => [__('ui.errors.area_required')]]], 422);
        }

        $result = $idempotency->execute($request->user(), 'case.draft', (string) $request->header('Idempotency-Key'), $data, function () use ($request, $data): array {
            $user = $request->user();
            $case = PatientCase::query()->create([
                'public_reference' => 'RD-'.strtoupper(Str::random(8)), 'patient_user_id' => $user->id,
                'service_type' => $data['service_type'], 'status' => CaseStatus::Draft, 'priority' => 'normal',
                'patient_name' => $data['name'] ?? $user->name, 'patient_mobile' => $user->phone,
                'patient_mobile_hash' => $user->phone_hash, 'tehran_area' => $data['tehran_area'] ?? null,
                'preferred_contact_time' => $data['preferred_contact_time'] ?? null, 'contact_reason' => $data['contact_reason'] ?? null,
                'budget_band' => $data['budget_band'], 'source_language' => $data['source_language'], 'budget_input_unit' => $data['budget_input_unit'], 'currency' => 'IRR',
            ]);

            return ['status' => 201, 'body' => ['data' => $this->resource($case)]];
        });

        return response()->json($result['body'], $result['status']);
    }

    public function submit(Request $request, PatientCase $case, Idempotency $idempotency, Outbox $outbox): JsonResponse
    {
        abort_unless((int) $case->patient_user_id === (int) $request->user()->id, 404);
        $data = $request->validate(['version' => ['required', 'integer', 'min:1'], 'policy_version' => ['required', 'string', 'max:50']]);
        $result = $idempotency->execute($request->user(), 'case.submit.'.$case->id, (string) $request->header('Idempotency-Key'), $data, function () use ($request, $case, $data, $outbox): array {
            return DB::transaction(function () use ($request, $case, $data, $outbox): array {
                $locked = PatientCase::query()->lockForUpdate()->findOrFail($case->id);
                if ($locked->status !== CaseStatus::Draft || $locked->version !== (int) $data['version']) {
                    abort(409);
                }
                $policy = PolicyVersion::query()->where('policy_key', 'case_coordination')->where('version', $data['policy_version'])->where('locale', $locked->source_language)->whereNotNull('published_at')->first();
                if (! $policy) {
                    return ['status' => 503, 'body' => ['error' => ['code' => 'error.consent.translation_unavailable']]];
                }
                DB::table('consent_events')->insert(['id' => (string) Str::ulid(), 'subject_user_id' => $request->user()->id, 'case_id' => $locked->id, 'policy_version_id' => $policy->id, 'purpose' => 'case_coordination', 'decision' => 'accepted', 'locale' => $locked->source_language, 'channel' => 'web', 'ip_hash' => hash_hmac('sha256', (string) $request->ip(), (string) config('app.key')), 'user_agent_hash' => hash('sha256', (string) $request->userAgent()), 'created_at' => now()]);
                $locked->update(['status' => CaseStatus::Submitted, 'version' => $locked->version + 1, 'submitted_at' => now()]);
                $outbox->record('case.submitted', PatientCase::class, $locked->id, ['template_key' => 'case_submitted', 'reference' => $locked->public_reference], 'case.submitted.'.$locked->id, $request->user()->locale);

                return ['status' => 200, 'body' => ['data' => $this->resource($locked->refresh())]];
            });
        });

        return response()->json($result['body'], $result['status']);
    }

    public function show(Request $request, PatientCase $case): JsonResponse
    {
        abort_unless($request->user()->can('view', $case), 404);

        return response()->json(['data' => $this->resource($case)])->header('Cache-Control', 'private, no-store');
    }

    private function resource(PatientCase $case): array
    {
        return ['id' => $case->id, 'reference' => $case->public_reference, 'service_type' => $case->service_type->value, 'status' => $case->status->value, 'version' => $case->version, 'source_language' => $case->source_language, 'submitted_at' => $case->submitted_at?->toIso8601String()];
    }
}
