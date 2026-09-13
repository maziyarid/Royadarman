<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Dashboard\DashboardService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->dashboard->build($request->user()),
        ]);
    }
}
