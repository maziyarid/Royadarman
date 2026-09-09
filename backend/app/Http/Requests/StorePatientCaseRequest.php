<?php

namespace App\Http\Requests;

use App\Domain\Cases\Enums\ServiceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePatientCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('mobile')) {
            return;
        }

        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $latin = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $mobile = str_replace([...$persian, ...$arabic], [...$latin, ...$latin], (string) $this->input('mobile'));
        $mobile = (string) preg_replace('/[^0-9+]+/', '', $mobile);
        $mobile = (string) preg_replace('/^(?:\+98|0098)/', '0', $mobile);

        $this->merge(['mobile' => $mobile]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'service_type' => ['required', Rule::enum(ServiceType::class)],
            'name' => ['nullable', 'string', 'max:80'],
            'mobile' => ['required', 'string', 'max:24', 'regex:/^(?:\+?98|0098|0)?9\d{9}$/'],
            'tehran_area' => ['nullable', 'string', Rule::in(config('royadarman.tehran_areas'))],
            'preferred_contact_time' => ['nullable', Rule::in(['any', 'morning', 'midday', 'evening', 'night'])],
            'contact_reason' => ['nullable', 'string', 'max:500'],
            'budget_band' => ['required', Rule::in(['economic', 'balanced', 'flexible', 'call'])],
            'consent' => ['accepted'],
            'policy_version' => ['required', 'string', 'max:50'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->string('service_type')->toString() === ServiceType::HomeDentistry->value && ! $this->filled('tehran_area')) {
                $validator->errors()->add('tehran_area', 'انتخاب محدوده تهران برای خدمت در منزل ضروری است.');
            }
        });
    }
}

