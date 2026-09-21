<?php

namespace App\Http\Controllers;

use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Jobs\SendChatMessageNotification;
use App\Models\Conversation;
use App\Models\User;
use App\Services\ConversationService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ConversationService $service,
    ) {
    }

    /**
     * List all conversations for the authenticated user.
     * GET /api/conversations
     *
     * Returns conversations sorted by latest activity (updated_at DESC).
     * Eager-loads participants + latest message to avoid N+1 queries.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Conversation::class);

        $userId = $request->user()->id;

        $conversations = Conversation::where('requester_id', $userId)
            ->orWhere('recipient_id', $userId)
            ->with([
                'requester.profile',
                'recipient.profile',
                'latestMessage',
            ])
            ->orderByDesc('updated_at')
            ->get();

        return $this->successResponse(
            data:    ['conversations' => ConversationResource::collection($conversations)],
            message: 'Conversations retrieved successfully.',
        );
    }

    /**
     * Find or create a conversation with another connected user.
     * POST /api/conversations
     *
     * Body: { "recipient_id": int }
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'recipient_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $otherUser = User::findOrFail($validated['recipient_id']);

        $conversation = $this->service->findOrCreate($request->user(), $otherUser);
        $conversation->load(['requester.profile', 'recipient.profile', 'latestMessage']);

        // 201 if newly created, 200 if it already existed.
        $status = $conversation->wasRecentlyCreated ? 201 : 200;

        return $this->successResponse(
            data:    ['conversation' => new ConversationResource($conversation)],
            message: $conversation->wasRecentlyCreated
                ? 'Conversation started.'
                : 'Conversation already exists.',
            status: $status,
        );
    }

    /**
     * Retrieve paginated message history for a conversation.
     * GET /api/conversations/{conversation}/messages
     *
     * Newest messages first. Follows the project's existing pagination contract.
     */
    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);
        $perPage = (int) ($validated['per_page'] ?? 20);

        $messages = $conversation->messages()
            ->with('sender.profile')
            ->reorder('created_at', 'desc')
            ->paginate($perPage);

        return $this->successResponse(
            data: [
                'items'      => MessageResource::collection($messages->items()),
                'pagination' => [
                    'total'        => $messages->total(),
                    'per_page'     => $messages->perPage(),
                    'current_page' => $messages->currentPage(),
                    'last_page'    => $messages->lastPage(),
                    'has_more'     => $messages->hasMorePages(),
                ],
            ],
            message: 'Messages retrieved successfully.',
        );
    }

    /**
     * Send a message in a conversation.
     * POST /api/conversations/{conversation}/messages
     *
     * Body: { "body": "Hello!" }
     */
    public function sendMessage(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('sendMessage', $conversation);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:' . ConversationService::MAX_BODY_LENGTH],
        ]);

        $message = $this->service->sendMessage(
            $conversation,
            $request->user(),
            $validated['body'],
        );

        $message->load('sender.profile');

        SendChatMessageNotification::dispatchAfterResponse($message);

        return $this->successResponse(
            data:    ['message' => new MessageResource($message)],
            message: 'Message sent.',
            status:  201,
        );
    }

    /**
     * Mark messages in a conversation as read.
     * POST /api/conversations/{conversation}/read
     */
    public function markAsRead(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('markAsRead', $conversation);

        $this->service->markAsRead($conversation, $request->user());

        return $this->successResponse(
            message: 'Messages marked as read.',
        );
    }

    /**
     * Retrieve a specific conversation by ID.
     * GET /api/conversations/{conversation}
     */
    public function show(Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $conversation->load(['requester.profile', 'recipient.profile', 'latestMessage']);

        return $this->successResponse(
            data:    ['conversation' => new ConversationResource($conversation)],
            message: 'Conversation retrieved successfully.',
        );
    }
}
