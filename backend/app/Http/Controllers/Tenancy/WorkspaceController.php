<?php

namespace App\Http\Controllers\Tenancy;

use App\Domain\Identity\Tenancy\TenancyService;
use App\Domain\Identity\Tenancy\WorkspaceAccess;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class WorkspaceController extends Controller
{
    public function __construct(private readonly TenancyService $tenancy, private readonly WorkspaceAccess $access) {}

    public function organisation(Request $request): JsonResponse
    {
        $data = $request->validate(['clinic_id' => ['required', 'ulid'], 'display_name' => ['required', 'string', 'max:160']]);
        $organisation = $this->tenancy->createOrganisation((int) $request->user()->id, $data['clinic_id'], $data['display_name']);

        return $this->json(['clinic_id' => $organisation->clinic_id, 'display_name' => $organisation->display_name], 201);
    }

    public function branch(Request $request, string $clinic): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'regex:/\A[a-z0-9][a-z0-9_-]{0,39}\z/D'], 'name' => ['required', 'string', 'max:160']]);
        $branch = $this->tenancy->createBranch((int) $request->user()->id, $clinic, $data['code'], $data['name']);

        return $this->json(['id' => $branch->id, 'clinic_id' => $branch->clinic_id, 'code' => $branch->code, 'name' => $branch->name], 201);
    }

    public function assign(Request $request, string $clinic): JsonResponse
    {
        $data = $request->validate(['branch_id' => ['required', 'ulid'], 'user_id' => ['required', 'integer', 'min:1'],
            'workspace_role' => ['required', Rule::in(WorkspaceAccess::ROLES)], 'active_until' => ['nullable', 'date', 'after:now']]);
        $membership = $this->tenancy->assign((int) $request->user()->id, $clinic, $data['branch_id'], (int) $data['user_id'], $data['workspace_role'],
            empty($data['active_until']) ? null : CarbonImmutable::parse($data['active_until'])->utc());

        return $this->json(['id' => $membership->id, 'clinic_id' => $membership->clinic_id, 'branch_id' => $membership->branch_id, 'version' => $membership->version], 201);
    }

    public function revoke(Request $request, string $clinic, string $membership): JsonResponse
    {
        $data = $request->validate(['version' => ['required', 'integer', 'min:1']]);
        $row = $this->tenancy->revoke((int) $request->user()->id, $clinic, $membership, (int) $data['version']);

        return $this->json(['id' => $row->id, 'version' => $row->version, 'revoked' => $row->revoked_at !== null]);
    }

    public function index(Request $request): JsonResponse
    {
        return $this->json($this->access->memberships((int) $request->user()->id));
    }

    public function select(Request $request): JsonResponse
    {
        $data = $request->validate(['membership_id' => ['required', 'ulid']]);
        $context = $this->access->resolve((int) $request->user()->id, $data['membership_id']);
        $request->session()->put(WorkspaceAccess::SESSION_KEY, ['membership_id' => $context->membershipId, 'membership_version' => $context->membershipVersion]);

        return $this->json($context);
    }

    public function current(Request $request): JsonResponse
    {
        $selected = $request->session()->get(WorkspaceAccess::SESSION_KEY);
        abort_unless(is_array($selected) && is_string($selected['membership_id'] ?? null) && is_int($selected['membership_version'] ?? null), 404);

        return $this->json($this->access->resolve((int) $request->user()->id, $selected['membership_id'], version: $selected['membership_version']));
    }

    private function json(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data], $status)->header('Cache-Control', 'private, no-store');
    }
}
