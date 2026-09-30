<?php

namespace App\Http\Controllers;

use App\Domain\Identity\Enums\UserRole;
use App\Support\WorkspaceView;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class PatientRequestController extends Controller
{
    public function create(Request $request, string $locale): View
    {
        abort_unless($request->user()?->role === UserRole::Patient, 404);

        return view('panel.patient-new-request', [
            ...WorkspaceView::data($request, 'request'),
            'locale' => $locale,
            'intakeEnabled' => (bool) config('royadarman.intake_enabled'),
            'tehranAreas' => config('royadarman.tehran_areas', []),
            'tehranNeighborhoods' => config('royadarman.tehran_neighborhoods', []),
        ]);
    }
}
