<?php

namespace App\Http\Controllers\Web;

use App\Domain\Identity\Enums\UserRole;
use App\Domain\Identity\Services\StaffCapabilities;
use App\Domain\Support\Enums\ConversationStatus;
use App\Domain\Support\Enums\SupportCategory;
use App\Domain\Support\Enums\SupportPriority;
use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\PatientCase;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\SupportStatusEvent;
use App\Models\User;
use App\Support\PanelDemoRegistry;
use App\Support\WorkspaceView;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class SupportWorkspaceController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless(
            in_array($user->role, [UserRole::Patient, UserRole::Coordinator], true)
            || StaffCapabilities::can($user->role, 'support.view'),
            403,
        );

        $base = SupportConversation::query()
            ->with(['patient:id,name,role', 'assignee:id,name,role', 'case:id,public_reference'])
            ->withCount(['messages as message_count'])
            ->when($user->role === UserRole::Patient, fn (Builder $q) => $q->where('patient_user_id', $user->id))
            ->when($user->role === UserRole::Coordinator, fn (Builder $q) => $q->where(
                fn (Builder $inner) => $inner->where('assignee_user_id', $user->id)->orWhereNull('assignee_user_id')
            ));

        if ((bool) $request->session()->get('panel_demo', false)) {
            $base->where(function (Builder $q): void {
                $q->whereNull('case_id')->orWhereHas(
                    'case',
                    fn (Builder $case) => $case->where('public_reference', 'like', PanelDemoRegistry::CASE_REFERENCE_PREFIX.'%'),
                );
            });
        }

        $summaryScope = clone $base;

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::enum(ConversationStatus::class)],
            'category' => ['nullable', Rule::enum(SupportCategory::class)],
            'priority' => ['nullable', Rule::enum(SupportPriority::class)],
            'assignment' => ['nullable', Rule::in(['mine', 'unassigned'])],
        ]);

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $base->where(function (Builder $q) use ($search): void {
                $q->where('subject', 'like', '%'.$search.'%')
                    ->orWhereHas('case', fn (Builder $case) => $case->where('public_reference', 'like', '%'.$search.'%'));
            });
        }

        foreach (['status', 'category', 'priority'] as $field) {
            if (! empty($filters[$field])) {
                $base->where($field, $filters[$field]);
            }
        }

        $assignment = (string) ($filters['assignment'] ?? '');
        if ($assignment === 'mine' && $user->role !== UserRole::Patient) {
            $base->where('assignee_user_id', $user->id);
        } elseif ($assignment === 'unassigned' && $user->role !== UserRole::Patient) {
            $base->whereNull('assignee_user_id');
        }

        $conversations = $base
            ->orderByRaw("case priority when 'urgent' then 0 when 'high' then 1 when 'normal' then 2 else 3 end")
            ->orderByRaw("case when status in ('open','reopened','in_progress') then 0 when status = 'awaiting_patient' then 1 else 2 end")
            ->orderByDesc('opened_at')
            ->paginate(30)
            ->withQueryString();

        return view('panel.support.index', [
            ...WorkspaceView::data($request, 'support'),
            'conversations' => $conversations,
            'filters' => [
                'q' => $search,
                'status' => (string) ($filters['status'] ?? ''),
                'category' => (string) ($filters['category'] ?? ''),
                'priority' => (string) ($filters['priority'] ?? ''),
                'assignment' => $assignment,
            ],
            'summary' => [
                'open' => (clone $summaryScope)->whereIn('status', ['open', 'reopened', 'in_progress'])->count(),
                'awaiting_patient' => (clone $summaryScope)->where('status', 'awaiting_patient')->count(),
                'unassigned' => (clone $summaryScope)->whereNull('assignee_user_id')->whereNotIn('status', ['resolved', 'closed'])->count(),
                'urgent' => (clone $summaryScope)->where('priority', 'urgent')->whereNotIn('status', ['resolved', 'closed'])->count(),
            ],
        ]);
    }

    public function show(Request $request, string $locale, SupportConversation $conversation): View
    {
        abort_unless($request->user()->can('view', $conversation), 404);

        $user = $request->user();
        $messages = $conversation->messages()
            ->with('author:id,name,role')
            ->when($user->role === UserRole::Patient, fn ($q) => $q->where('is_internal', false))
            ->orderBy('created_at')
            ->get();

        $conversation->load(['case:id,public_reference,service_type,status', 'patient:id,name', 'assignee:id,name']);

        $activeCoordinators = collect();
        if ($user->role === UserRole::Owner && ! $request->session()->get('panel_demo', false)) {
            $activeCoordinators = User::query()
                ->where('role', UserRole::Coordinator->value)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        return view('panel.support.show', [
            ...WorkspaceView::data($request, 'support'),
            'conversation' => $conversation,
            'messages' => $messages,
            'activeCoordinators' => $activeCoordinators,
        ]);
    }

    public function reply(Request $request, string $locale, SupportConversation $conversation): RedirectResponse
    {
        abort_unless($request->user()->can('reply', $conversation), 404);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
        ]);

        DB::transaction(function () use ($request, $conversation, $data): void {
            SupportMessage::query()->create([
                'conversation_id' => $conversation->id,
                'author_user_id' => $request->user()->id,
                'is_internal' => false,
                'body' => $data['message'],
                'source_language' => app()->getLocale(),
                'created_at' => now(),
            ]);

            if ($conversation->first_response_at === null && $request->user()->role !== UserRole::Patient) {
                $conversation->update(['first_response_at' => now()]);
            }
        });

        return back()->with('status', __('panel.saved'));
    }

    public function internalNote(Request $request, string $locale, SupportConversation $conversation): RedirectResponse
    {
        abort_unless($request->user()->can('addInternalNote', $conversation), 403);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
        ]);

        SupportMessage::query()->create([
            'conversation_id' => $conversation->id,
            'author_user_id' => $request->user()->id,
            'is_internal' => true,
            'body' => $data['message'],
            'source_language' => app()->getLocale(),
            'created_at' => now(),
        ]);

        $this->audit($request, 'support.internal_note.created', $conversation);

        return back()->with('status', __('panel.saved'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->role === UserRole::Patient, 403);

        $data = $request->validate([
            'case_id' => ['nullable', 'ulid'],
            'subject' => ['nullable', 'string', 'max:200'],
            'category' => ['required', Rule::enum(SupportCategory::class)],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $caseId = null;
        if (! empty($data['case_id'])) {
            $caseId = PatientCase::query()
                ->whereKey($data['case_id'])
                ->where('patient_user_id', $user->id)
                ->value('id');
            abort_if($caseId === null, 422);
        }

        $conversation = DB::transaction(function () use ($user, $data, $caseId): SupportConversation {
            $conversation = SupportConversation::query()->create([
                'patient_user_id' => $user->id,
                'case_id' => $caseId,
                'subject' => $data['subject'] ?? null,
                'category' => $data['category'],
                'status' => ConversationStatus::Open,
                'priority' => SupportPriority::Normal,
                'opened_at' => now(),
                'source_language' => app()->getLocale(),
            ]);

            SupportMessage::query()->create([
                'conversation_id' => $conversation->id,
                'author_user_id' => $user->id,
                'is_internal' => false,
                'body' => $data['message'],
                'source_language' => app()->getLocale(),
                'created_at' => now(),
            ]);

            return $conversation;
        });

        return redirect()
            ->route('panel.support.show', ['locale' => app()->getLocale(), 'conversation' => $conversation->id])
            ->with('status', __('panel.saved'));
    }

    public function status(Request $request, string $locale, SupportConversation $conversation): RedirectResponse
    {
        abort_unless($request->user()->can('changeStatus', $conversation), 403);

        $data = $request->validate([
            'status' => ['required', Rule::enum(ConversationStatus::class)],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        $target = ConversationStatus::from($data['status']);
        abort_unless($conversation->status->canTransitionTo($target), 409);

        DB::transaction(function () use ($request, $conversation, $target, $data): void {
            $from = $conversation->status;
            $conversation->update([
                'status' => $target,
                'resolved_at' => $target === ConversationStatus::Resolved ? now() : $conversation->resolved_at,
                'closed_at' => $target === ConversationStatus::Closed ? now() : $conversation->closed_at,
            ]);

            SupportStatusEvent::query()->create([
                'conversation_id' => $conversation->id,
                'actor_user_id' => $request->user()->id,
                'from_status' => $from->value,
                'to_status' => $target->value,
                'reason' => $data['reason'] ?? null,
                'created_at' => now(),
            ]);
        });

        return back()->with('status', __('panel.saved'));
    }

    public function assign(Request $request, string $locale, SupportConversation $conversation): RedirectResponse
    {
        abort_unless($request->user()->can('assign', $conversation), 403);

        $actor = $request->user();
        $assigneeId = $actor->id;

        if ($actor->role === UserRole::Owner) {
            $data = $request->validate([
                'assignee_user_id' => ['nullable', 'integer'],
            ]);

            $assigneeId = null;
            if (! empty($data['assignee_user_id'])) {
                $assigneeId = User::query()
                    ->whereKey($data['assignee_user_id'])
                    ->where('role', UserRole::Coordinator->value)
                    ->where('is_active', true)
                    ->value('id');
                abort_if($assigneeId === null, 422);
            }
        }

        $conversation->update(['assignee_user_id' => $assigneeId]);
        $this->audit($request, 'support.assignment.changed', $conversation, ['assignee_user_id' => $assigneeId]);

        return back()->with('status', __('panel.saved'));
    }

    public function priority(Request $request, string $locale, SupportConversation $conversation): RedirectResponse
    {
        abort_unless($request->user()->can('changePriority', $conversation), 403);

        $data = $request->validate([
            'priority' => ['required', Rule::enum(SupportPriority::class)],
        ]);

        $conversation->update(['priority' => $data['priority']]);
        $this->audit($request, 'support.priority.changed', $conversation, ['priority' => $data['priority']]);

        return back()->with('status', __('panel.saved'));
    }

    private function audit(Request $request, string $action, SupportConversation $conversation, array $context = []): void
    {
        AuditEvent::query()->create([
            'actor_user_id' => $request->user()?->id,
            'action' => $action,
            'resource_type' => 'support_conversation',
            'resource_id' => (string) $conversation->id,
            'result' => 'success',
            'reason' => null,
            'context' => ['source' => 'support_workspace', ...$context],
            'correlation_id' => (string) Str::ulid(),
            'created_at' => now(),
        ]);
    }
}
