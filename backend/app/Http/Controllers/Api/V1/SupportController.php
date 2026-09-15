<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Enums\UserRole;
use App\Domain\Support\Enums\ConversationStatus;
use App\Domain\Support\Enums\SupportCategory;
use App\Http\Controllers\Controller;
use App\Models\PatientCase;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\SupportStatusEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class SupportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = SupportConversation::query()
            ->with(['patient:id,role', 'assignee:id,role'])
            ->orderByDesc('opened_at');

        if ($user->role === UserRole::Patient) {
            $query->where('patient_user_id', $user->id);
        } elseif ($user->role === UserRole::Coordinator) {
            $query->where(function ($q) use ($user) {
                $q->where('assignee_user_id', $user->id)
                    ->orWhereNull('assignee_user_id');
            });
        } elseif ($user->role === UserRole::Owner || $user->role === UserRole::TechnicalAdministrator) {
            // Owners and technical administrators may browse all support conversations.
        } else {
            abort(403);
        }

        $conversations = $query->paginate(20);

        return response()->json([
            'data' => $conversations->map(fn ($c) => $this->summary($c)),
            'meta' => ['page' => $conversations->currentPage(), 'last' => $conversations->lastPage()],
        ])->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->role === UserRole::Patient, 403);

        $data = $request->validate([
            'case_id' => ['nullable', 'ulid'],
            'subject' => ['nullable', 'string', 'max:200'],
            'category' => ['required', Rule::enum(SupportCategory::class)],
            'source_language' => ['required', 'in:fa,ar,en'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $caseId = null;
        if (! empty($data['case_id'])) {
            $caseId = PatientCase::query()
                ->whereKey($data['case_id'])
                ->where('patient_user_id', $user->id)
                ->value('id');
            if ($caseId === null) {
                abort(422, __('ui.errors.case_not_owned'));
            }
        }

        $conversation = SupportConversation::query()->create([
            'patient_user_id' => $user->id,
            'case_id' => $caseId,
            'subject' => $data['subject'] ?? null,
            'category' => $data['category'],
            'status' => ConversationStatus::Open,
            'priority' => 'normal',
            'opened_at' => now(),
            'source_language' => $data['source_language'],
        ]);

        SupportMessage::query()->create([
            'conversation_id' => $conversation->id,
            'author_user_id' => $user->id,
            'is_internal' => false,
            'body' => $data['message'],
            'source_language' => $data['source_language'],
            'created_at' => now(),
        ]);

        return response()->json(['data' => ['id' => $conversation->id, 'status' => $conversation->status->value]], 201);
    }

    public function show(Request $request, SupportConversation $conversation): JsonResponse
    {
        abort_unless($request->user()->can('view', $conversation), 404);

        $user = $request->user();
        $messages = $conversation->messages()
            ->when($user->role === UserRole::Patient, fn ($q) => $q->where('is_internal', false))
            ->orderBy('created_at')
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'author_user_id' => $m->author_user_id,
                'is_internal' => $m->is_internal,
                'is_own' => $m->author_user_id === $user->id,
                'body' => $m->body,
                'created_at' => $m->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'data' => [
                'id' => $conversation->id,
                'subject' => $conversation->subject,
                'category' => $conversation->category->value,
                'status' => $conversation->status->value,
                'priority' => $conversation->priority->value,
                'source_language' => $conversation->source_language,
                'opened_at' => $conversation->opened_at?->toIso8601String(),
                'messages' => $messages,
            ],
        ])->header('Cache-Control', 'private, no-store');
    }

    public function reply(Request $request, SupportConversation $conversation): JsonResponse
    {
        abort_unless($request->user()->can('reply', $conversation), 404);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
            'source_language' => ['required', 'in:fa,ar,en'],
        ]);

        $message = SupportMessage::query()->create([
            'conversation_id' => $conversation->id,
            'author_user_id' => $request->user()->id,
            'is_internal' => false,
            'body' => $data['message'],
            'source_language' => $data['source_language'],
            'created_at' => now(),
        ]);

        if ($conversation->first_response_at === null && $request->user()->role !== UserRole::Patient) {
            $conversation->update(['first_response_at' => now()]);
        }

        return response()->json(['data' => ['id' => $message->id]], 201);
    }

    public function internalNote(Request $request, SupportConversation $conversation): JsonResponse
    {
        abort_unless($request->user()->can('addInternalNote', $conversation), 403);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
            'source_language' => ['required', 'in:fa,ar,en'],
        ]);

        $message = SupportMessage::query()->create([
            'conversation_id' => $conversation->id,
            'author_user_id' => $request->user()->id,
            'is_internal' => true,
            'body' => $data['message'],
            'source_language' => $data['source_language'],
            'created_at' => now(),
        ]);

        return response()->json(['data' => ['id' => $message->id, 'is_internal' => true]], 201);
    }

    public function status(Request $request, SupportConversation $conversation): JsonResponse
    {
        abort_unless($request->user()->can('changeStatus', $conversation), 403);

        $data = $request->validate([
            'status' => ['required', Rule::enum(ConversationStatus::class)],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        $target = ConversationStatus::from($data['status']);
        if (! $conversation->status->canTransitionTo($target)) {
            return response()->json(['error' => ['code' => 'support.invalid_transition']], 409);
        }

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

        return response()->json(['data' => ['id' => $conversation->id, 'status' => $target->value]]);
    }

    public function assign(Request $request, SupportConversation $conversation): JsonResponse
    {
        $user = $request->user();
        abort_unless(in_array($user->role, [UserRole::Coordinator, UserRole::Owner], true), 403);
        abort_unless($user->can('assign', $conversation), 403);

        $data = $request->validate([
            'assignee_user_id' => ['required', 'exists:users,id'],
        ]);

        $conversation->update(['assignee_user_id' => $data['assignee_user_id']]);

        return response()->json(['data' => ['id' => $conversation->id]]);
    }

    private function summary(SupportConversation $c): array
    {
        return [
            'id' => $c->id,
            'subject' => $c->subject,
            'category' => $c->category->value,
            'status' => $c->status->value,
            'priority' => $c->priority->value,
            'opened_at' => $c->opened_at?->toIso8601String(),
        ];
    }
}
