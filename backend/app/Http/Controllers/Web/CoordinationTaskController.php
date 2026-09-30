<?php

namespace App\Http\Controllers\Web;

use App\Domain\Identity\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\CoordinationTask;
use App\Models\PatientCase;
use App\Support\WorkspaceView;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class CoordinationTaskController extends Controller
{
    public function index(Request $request, string $locale): View
    {
        $user = $request->user();
        $this->authorizeCoordinator($request);

        $status = (string) $request->query('status', '');
        $query = CoordinationTask::query()
            ->with('case:id,public_reference,status,service_type,current_coordinator_id')
            ->where('assignee_user_id', $user->id)
            ->whereHas('case', fn ($q) => $q->where('current_coordinator_id', $user->id))
            ->orderByRaw('due_at IS NULL')
            ->orderBy('due_at')
            ->latest('updated_at');

        if (in_array($status, ['open', 'in_progress', 'done'], true)) {
            $query->where('status', $status);
        }

        $cases = PatientCase::query()
            ->where('current_coordinator_id', $user->id)
            ->whereNotIn('status', ['closed', 'cancelled'])
            ->latest('updated_at')
            ->limit(100)
            ->get(['id', 'public_reference', 'status', 'service_type']);

        $all = CoordinationTask::query()
            ->where('assignee_user_id', $user->id)
            ->whereHas('case', fn ($q) => $q->where('current_coordinator_id', $user->id))
            ->get(['status', 'due_at']);

        return view('panel.tasks.index', [
            ...WorkspaceView::data($request, 'tasks'),
            'tasks' => $query->paginate(30)->withQueryString(),
            'cases' => $cases,
            'filterStatus' => $status,
            'summary' => [
                'open' => $all->where('status', 'open')->count(),
                'in_progress' => $all->where('status', 'in_progress')->count(),
                'done' => $all->where('status', 'done')->count(),
                'overdue' => $all->filter(fn ($task) => $task->status !== 'done' && $task->due_at?->isPast())->count(),
                'due_today' => $all->filter(fn ($task) => $task->status !== 'done' && $task->due_at?->isToday())->count(),
            ],
        ])->with('locale', $locale);
    }

    public function store(Request $request, string $locale): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeCoordinator($request);

        $data = $request->validate([
            'case_id' => ['required', 'ulid'],
            'task_type' => ['required', Rule::in(['follow_up', 'support', 'referral', 'home_service', 'document', 'other'])],
            'operational_note' => ['nullable', 'string', 'max:1000'],
            'due_at' => ['nullable', 'date'],
        ]);

        $case = PatientCase::query()
            ->whereKey($data['case_id'])
            ->where('current_coordinator_id', $user->id)
            ->firstOrFail();

        $task = DB::transaction(function () use ($data, $case, $user): CoordinationTask {
            $task = CoordinationTask::query()->create([
                'case_id' => $case->id,
                'assignee_user_id' => $user->id,
                'task_type' => $data['task_type'],
                'status' => 'open',
                'operational_note' => $data['operational_note'] ?? null,
                'due_at' => $data['due_at'] ?? null,
            ]);

            $this->audit($user->id, 'coordination.task.created', $task);

            return $task;
        });

        return redirect()
            ->route('panel.tasks.index', ['locale' => $locale])
            ->with('status', __('panel.saved'));
    }

    public function update(Request $request, string $locale, CoordinationTask $task): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeTask($request, $task);

        $data = $request->validate([
            'status' => ['required', Rule::in(['open', 'in_progress', 'done'])],
            'operational_note' => ['nullable', 'string', 'max:1000'],
            'due_at' => ['nullable', 'date'],
        ]);

        DB::transaction(function () use ($task, $user, $data): void {
            $task->update([
                'status' => $data['status'],
                'operational_note' => $data['operational_note'] ?? null,
                'due_at' => $data['due_at'] ?? null,
            ]);

            $this->audit($user->id, 'coordination.task.updated', $task, ['status' => $data['status']]);
        });

        return redirect()
            ->route('panel.tasks.index', ['locale' => $locale])
            ->with('status', __('panel.saved'));
    }

    public function destroy(Request $request, string $locale, CoordinationTask $task): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeTask($request, $task);

        DB::transaction(function () use ($task, $user): void {
            $taskId = (string) $task->id;
            $task->delete();

            AuditEvent::query()->create([
                'actor_user_id' => $user->id,
                'action' => 'coordination.task.deleted',
                'resource_type' => 'coordination_task',
                'resource_id' => $taskId,
                'result' => 'success',
                'reason' => null,
                'context' => ['source' => 'coordinator_workspace'],
                'correlation_id' => (string) Str::ulid(),
                'created_at' => now(),
            ]);
        });

        return redirect()
            ->route('panel.tasks.index', ['locale' => $locale])
            ->with('status', __('panel.saved'));
    }

    private function authorizeCoordinator(Request $request): void
    {
        abort_unless(
            $request->user()?->is_active
            && $request->user()->role === UserRole::Coordinator
            && ! $request->session()->get('panel_demo', false),
            403,
        );
    }

    private function authorizeTask(Request $request, CoordinationTask $task): void
    {
        $this->authorizeCoordinator($request);

        abort_unless(
            $task->assignee_user_id === $request->user()->id
            && PatientCase::query()
                ->whereKey($task->case_id)
                ->where('current_coordinator_id', $request->user()->id)
                ->exists(),
            404,
        );
    }

    private function audit(int $actorId, string $action, CoordinationTask $task, array $context = []): void
    {
        AuditEvent::query()->create([
            'actor_user_id' => $actorId,
            'action' => $action,
            'resource_type' => 'coordination_task',
            'resource_id' => (string) $task->id,
            'result' => 'success',
            'reason' => null,
            'context' => ['source' => 'coordinator_workspace', ...$context],
            'correlation_id' => (string) Str::ulid(),
            'created_at' => now(),
        ]);
    }
}
