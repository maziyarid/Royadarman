<?php

namespace App\Http\Controllers\Web;

use App\Domain\Identity\Enums\UserRole;
use App\Domain\Patients\IranLocations;
use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\PatientCase;
use App\Models\PatientContactProfile;
use App\Models\SupportConversation;
use App\Models\User;
use App\Support\WorkspaceView;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PatientPortalController extends Controller
{
    private function guard(Request $request): void
    {
        abort_unless($request->user()?->is_active && $request->user()->role === UserRole::Patient, 404);
        abort_if($request->session()->get('panel_demo', false), 403);
    }

    public function home(Request $request): Response
    {
        $this->guard($request);
        $user = $request->user();
        $ownCases = PatientCase::query()->where('patient_user_id', $user->id);
        $cases = (clone $ownCases)->withCount(['documents' => fn ($q) => $q->whereNull('deleted_at')])->latest()->paginate(20);
        $support = SupportConversation::query()->where('patient_user_id', $user->id)->latest('opened_at')->limit(8)->get();
        $profile = PatientContactProfile::query()->find($user->id);
        $profileComplete = filled($user->name) && $profile !== null && filled($profile->province) && filled($profile->city);
        return response()->view('patient-portal.home', [
            ...WorkspaceView::data($request, 'panel'),
            'patient' => $user, 'profile' => $profile, 'profileComplete' => $profileComplete,
            'cases' => $cases, 'conversations' => $support,
            'activeCases' => (clone $ownCases)->whereNotIn('status', ['closed', 'cancelled'])->count(),
            'documentCount' => DB::table('clinical_documents')->join('patient_cases', 'patient_cases.id', '=', 'clinical_documents.case_id')
                ->where('patient_cases.patient_user_id', $user->id)->whereNull('clinical_documents.deleted_at')->count(),
        ])->header('Cache-Control', 'private, no-store')->header('Pragma', 'no-cache');
    }

    public function profile(Request $request): Response
    {
        $this->guard($request);
        return response()->view('patient-portal.profile', [
            ...WorkspaceView::data($request, 'profile'), 'patient' => $request->user(),
            'profile' => PatientContactProfile::query()->find($request->user()->id),
            'provinces' => app(IranLocations::class)->provinces(),
        ])->header('Cache-Control', 'private, no-store')->header('Pragma', 'no-cache');
    }

    public function saveProfile(Request $request): RedirectResponse
    {
        $this->guard($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'contact_email' => ['nullable', 'email:rfc', 'max:254'],
            'birth_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today', 'after_or_equal:1900-01-01'],
            'postal_code' => ['nullable', 'string', 'regex:/^[0-9۰-۹٠-٩]{10}$/u'],
            'preferred_contact_time' => ['nullable', 'in:any,morning,midday,evening,night'],
            'version' => ['required', 'integer', 'min:0'],
        ]);
        $locations = app(IranLocations::class);
        $location = $locations->validate($request->all(), true);
        DB::transaction(function () use ($request, $data, $location, $locations): void {
            // Serialise even first creation, without accepting a caller-supplied user id.
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            $existing = PatientContactProfile::query()->whereKey($user->id)->lockForUpdate()->first();
            abort_unless((int) ($existing?->version ?? 0) === (int) $data['version'], 409, __('patient_portal.stale_profile'));
            PatientContactProfile::query()->updateOrCreate(['user_id' => $user->id], [
                ...$locations->snapshot($location), 'contact_email' => $data['contact_email'] ?? null,
                'birth_date' => $data['birth_date'] ?? null, 'postal_code' => $data['postal_code'] ?? null,
                'preferred_contact_time' => $data['preferred_contact_time'] ?? 'any', 'version' => (int) $data['version'] + 1,
            ]);
            $user->update(['name' => $data['name']]);
            AuditEvent::query()->create([
                'actor_user_id' => $user->id, 'action' => 'patient.profile.updated',
                'resource_type' => PatientContactProfile::class, 'resource_id' => (string) $user->id,
                'result' => 'success', 'context' => ['fields' => array_merge(array_keys($data), array_keys($location))],
                'correlation_id' => (string) Str::ulid(), 'created_at' => now(),
            ]);
        });
        return redirect()->route('patient.profile', ['locale' => app()->getLocale()])->with('status', __('patient_portal.saved'));
    }

    public function locations(Request $request): JsonResponse
    {
        $this->guard($request);
        return response()->json(['data' => app(IranLocations::class)->all()])->header('Cache-Control', 'private, no-store');
    }
}
