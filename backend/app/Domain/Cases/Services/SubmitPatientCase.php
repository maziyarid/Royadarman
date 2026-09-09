<?php

namespace App\Domain\Cases\Services;

use App\Domain\Cases\Enums\CaseStatus;
use App\Domain\Cases\Enums\ServiceType;
use App\Models\AuditEvent;
use App\Models\ConsentRecord;
use App\Models\PatientCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SubmitPatientCase
{
    /** @param array<string, mixed> $data */
    public function handle(array $data, string $ipAddress, string $userAgent): PatientCase
    {
        return DB::transaction(function () use ($data, $ipAddress, $userAgent): PatientCase {
            $mobile = $this->normaliseMobile((string) $data['mobile']);
            $case = PatientCase::query()->create([
                'public_reference' => $this->reference(),
                'service_type' => ServiceType::from($data['service_type']),
                'status' => CaseStatus::Submitted,
                'priority' => 'normal',
                'patient_name' => $data['name'] ?? null,
                'patient_mobile' => $mobile,
                'patient_mobile_hash' => hash_hmac('sha256', $mobile, (string) config('app.key')),
                'tehran_area' => $data['tehran_area'] ?? null,
                'preferred_contact_time' => $data['preferred_contact_time'] ?? null,
                'contact_reason' => $data['contact_reason'] ?? null,
                'budget_band' => $data['budget_band'],
                'submitted_at' => now(),
            ]);

            ConsentRecord::query()->create([
                'case_id' => $case->id,
                'purpose' => 'case_coordination',
                'policy_version' => $data['policy_version'],
                'accepted' => true,
                'evidence' => [
                    'ip_hash' => hash_hmac('sha256', $ipAddress, (string) config('app.key')),
                    'user_agent_hash' => hash('sha256', $userAgent),
                    'channel' => 'web',
                ],
                'decided_at' => now(),
            ]);

            AuditEvent::query()->create([
                'action' => 'case.submitted',
                'resource_type' => PatientCase::class,
                'resource_id' => $case->id,
                'result' => 'success',
                'context' => ['service_type' => $case->service_type->value],
                'correlation_id' => (string) Str::ulid(),
                'created_at' => now(),
            ]);

            return $case;
        });
    }

    private function normaliseMobile(string $value): string
    {
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $latin = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $normalised = str_replace([...$persian, ...$arabic], [...$latin, ...$latin], $value);

        return (string) preg_replace('/\D+/', '', $normalised);
    }

    private function reference(): string
    {
        do {
            $reference = 'RD-'.strtoupper(Str::random(8));
        } while (PatientCase::query()->where('public_reference', $reference)->exists());

        return $reference;
    }
}

