<?php

namespace App\Http\Controllers\Web;

use App\Domain\Identity\Enums\UserRole;
use App\Domain\Operations\Services\OperationsAnalytics;
use App\Http\Controllers\Controller;
use App\Support\WorkspaceView;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class OperationsAnalyticsController extends Controller
{
    public function index(Request $request, string $locale, OperationsAnalytics $analytics): Response
    {
        $user = $request->user();
        abort_unless(
            $user?->is_active
            && $user->role === UserRole::Owner
            && ! $request->session()->get('panel_demo', false),
            403,
        );

        return response()->view('panel.analytics.index', [
            ...WorkspaceView::data($request, 'analytics'),
            ...$analytics->report($request->query('range', '30d')),
            'locale' => $locale,
        ])->header('Cache-Control', 'private, no-store');
    }
}
