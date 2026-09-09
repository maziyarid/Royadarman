<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Cases\Services\SubmitPatientCase;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePatientCaseRequest;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class PatientCaseController extends Controller
{
    public function store(StorePatientCaseRequest $request, SubmitPatientCase $submit): JsonResponse
    {
        $case = $submit->handle(
            $request->safe()->except('consent'),
            (string) $request->ip(),
            (string) $request->userAgent(),
        );

        return new JsonResponse([
            'data' => [
                'reference' => $case->public_reference,
                'service_type' => $case->service_type->value,
                'status' => $case->status->value,
                'submitted_at' => $case->submitted_at?->toIso8601String(),
            ],
        ], Response::HTTP_CREATED);
    }
}

