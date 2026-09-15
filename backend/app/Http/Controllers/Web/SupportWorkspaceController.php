<?php

namespace App\Http\Controllers\Web;

use App\Domain\Identity\Enums\UserRole;
use App\Domain\Support\Enums\ConversationStatus;
use App\Domain\Support\Enums\SupportCategory;
use App\Http\Controllers\Controller;
use App\Models\PatientCase;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\SupportStatusEvent;
use App\Support\PanelDemoRegistry;
use App\Support\WorkspaceView;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class SupportWorkspaceController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless(in_array($user->role, [
            UserRole::Patient,
            UserRole::Coordinator,
            UserRole::Owner,
            UserRole::TechnicalAdministrator,
        ], true), 403);

        $query = SupportConversation::query()
            ->with(['patient:id,name,role', 'assignee:id,name,role', 'case:id,public_reference'])
            ->orderByDesc('opened_at');

        if ($user->role === UserRole::Patient) {
            $query->where('patient_user_id', $user->id);
        } elseif ($user->role === UserRole::Coordinator) {
            $query->where(function ($q) use ($user): void {
                $q->where('assignee_user_id', $user->id)->orWhereNull('assignee_user_id');
            });
        }

        if ((bool) $request->session()->get('panel_demo', false)) {
            $query->where(function ($q): void {
                $q->whereNull('case_id')->orWhereHas(
                    'case',
                    fn ($case) => $case->where('public_reference', 'like', PanelDemoRegistry::CASE_REFERENCE_PREFIX.'%'),
                );
            });
        }

        $status = (string) $request->query('status', '');
        if ($status !== '' && in_array($status, array_column(ConversationStatus::cases(), 'value'), true)) {
            $query->where('status', $status);
        }

        return view('panel.support.index', [
            ...WorkspaceView::data($request, 'support'),
            'conversations' => $query->paginate(20)->withQueryString(),
            'filterStatus' => $status,
        ]);
    }

    public function show(Request $request, string $locale, SupportConversation $conversation): View
    {
        abort_unless($request->user()->can('view', $conversation), 404);

        $user = $request->user();
        $messages = $conversation->messages()
            ->when($user->role === UserRole::Patient, fn ($q) => $q->where('is_internal', false))
            ->orderBy('created_at')
            ->get();

        $conversation->load(['case:id,public_reference,service_type,status', 'patient:id,name', 'assignee:id,name']);

        return view('panel.support.show', [
            ...WorkspaceView::data($request, 'support'),
            'conversation' => $conversation,
            'messages' => $messages,
        ]);
    }

    public function reply(Request $request, string $locale, SupportConversation $conversation): RedirectResponse
    {
        abort_unless($request->user()->can('reply', $conversation), 404);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
        ]);

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

        $conversation = SupportConversation::query()->create([
            'patient_user_id' => $user->id,
            'case_id' => $caseId,
            'subject' => $data['subject'] ?? null,
            'category' => $data['category'],
            'status' => ConversationStatus::Open,
            'priority' => 'normal',
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

        return back()->with('status', __('panel.saved'));
    }

    public function assign(Request $request, string $locale, SupportConversation $conversation): RedirectResponse
    {
        abort_unless($request->user()->can('assign', $conversation), 403);

        $conversation->update(['assignee_user_id' => $request->user()->id]);

        return back()->with('status', __('panel.saved'));
    }
}
