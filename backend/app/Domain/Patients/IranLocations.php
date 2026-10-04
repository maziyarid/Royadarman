<?php

namespace App\Domain\Patients;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class IranLocations
{
    public function all(): array
    {
        return json_decode(file_get_contents(resource_path('data/iran-locations.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    public function provinces(): array
    {
        return array_column($this->all(), 'province-fa', 'province-en');
    }

    public function validate(array $input, bool $required = false): array
    {
        $data = Validator::make($input, [
            'province' => [$required ? 'required' : 'nullable', 'string', Rule::in(array_keys($this->provinces()))],
            'city' => ['required_with:province', 'nullable', 'string', 'max:100'],
            'neighborhood' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:1000'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:24,41'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:43,64'],
            'location_consent' => ['nullable', 'boolean'],
        ])->validate();
        if (filled($data['latitude'] ?? null) && empty($data['location_consent'])) {
            throw ValidationException::withMessages(['location_consent' => __('patient_portal.location_consent_required')]);
        }
        if (empty($data['province'])) {
            return [];
        }
        $data['city'] = trim($data['city']);
        foreach ($this->all() as $province) {
            if ($province['province-en'] !== $data['province']) {
                continue;
            }
            foreach ($province['cities'] as $city) {
                if (in_array($this->normalize($data['city']), [$this->normalize($city['city-fa']), $this->normalize($city['city-en'])], true)) {
                    $data['city'] = $city['city-fa'];
                    break;
                }
            }
        }
        if ($data['city'] === '') {
            throw ValidationException::withMessages(['city' => __('patient_portal.city_required')]);
        }
        return $data;
    }

    public function isTehranCity(array $location): bool
    {
        return ($location['province'] ?? null) === 'tehran'
            && in_array($this->normalize((string) ($location['city'] ?? '')), ['تهران', 'tehran'], true);
    }

    public function normalize(string $value): string
    {
        return mb_strtolower(str_replace(['ي', 'ك', '‌', ' ', '-'], ['ی', 'ک', '', '', ''], trim($value)));
    }

    public function snapshot(array $data): array
    {
        return [
            'province' => $data['province'], 'city' => $data['city'],
            'neighborhood' => $data['neighborhood'] ?? null, 'address' => $data['address'] ?? null,
            'latitude' => $data['latitude'] ?? null, 'longitude' => $data['longitude'] ?? null,
            'location_consented_at' => filled($data['latitude'] ?? null) && ! empty($data['location_consent']) ? now() : null,
        ];
    }
}
