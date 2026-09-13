<?php

namespace App\Http\Controllers\Web;

use App\Domain\Dashboard\DashboardService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    public function show(Request $request): View
    {
        $user = $request->user();
        $payload = $this->dashboard->build($user);
        $role = $payload['role'];

        return view("dashboard.{$role}", [
            'data' => $payload,
            'user' => $user,
        ]);
    }
}
