<?php

namespace App\Services;

use App\Enums\ConnectionStatus;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\ConnectionRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Centralises all business rules for the Conversation/Message lifecycle.
 *
 * Design mirrors ConnectionRequestService and TripJoinRequestService:
 *   - service throws HttpException for business-rule violations
 *   - policy (ConversationPolicy) handles authorization (403)
 *   - controller is thin; delegates all logic here
 *
 * Duplicate conversation prevention:
 * ────────────────────────────────────
 * Conversations are stored with a canonical key: requester_id = min(A,B),
 * recipient_id = max(A,B). The DB has a UNIQUE(requester_id, recipient_id)
 * constraint. findOrCreate uses a DB transaction with updateOrCreate so that
 * concurrent requests for the same pair resolve to a single row.
 *
 * Accepted-connection rule:
 * ─────────────────────────
 * Only users with a status=accepted ConnectionRequest (in either direction)
 * may start or participate in a conversation.
 */
class ConversationService
{
    // Maximum message body length in characters.
    public const MAX_BODY_LENGTH = 5000;

    // ── Public API ─────────────────────────────────────────────────────────────

    /**
     * Find an existing conversation between the two users, or create one.
     *
     * Rules enforced:
     *   1. User cannot start a conversation with themselves.
     *   2. Both users must have an accepted ConnectionRequest (bidirectional check).
     *   3. Only one conversation per pair (canonical ordering + DB unique).
     *
     * @throws HttpException (409) for self-conversation or no accepted connection
     */
    public function findOrCreate(User $user, User $otherUser): Conversation
    {
        if ($user->id === $otherUser->id) {
            throw new HttpException(409, 'You cannot start a conversation with yourself.');
        }

        if (! $this->areConnected($user, $otherUser)) {
            throw new HttpException(409, 'You can only message users you are connected with.');
        }

        // Canonical ordering: always store the lower ID as requester_id.
        // This ensures UNIQUE(requester_id, recipient_id) is sufficient.
        [$requesterId, $recipientId] = $this->canonicalPair($user->id, $otherUser->id);

        return DB::transaction(function () use ($requesterId, $recipientId) {
            return Conversation::firstOrCreate(
                ['requester_id' => $requesterId, 'recipient_id' => $recipientId],
            );
        });
    }

    /**
     * Send a text message in a conversation.
     *
     * Rules enforced:
     *   1. Sender must be a participant of the conversation.
     *   2. The accepted connection must still exist.
     *   3. Body must not be empty after trimming.
     *   4. Body must not exceed MAX_BODY_LENGTH characters.
     *
     * @throws HttpException (409) for authorization or connection issues
     * @throws HttpException (422) for validation issues
     */
    public function sendMessage(Conversation $conversation, User $sender, string $body): Message
    {
        if (! $conversation->hasParticipant($sender)) {
            throw new HttpException(403, 'You are not a participant in this conversation.');
        }

        // Re-verify connection is still accepted at send time.
        $otherUser = $conversation->otherParticipant($sender);
        if (! $this->areConnected($sender, $otherUser)) {
            throw new HttpException(409, 'Cannot send message: you are no longer connected with this user.');
        }

        $body = trim($body);

        if ($body === '') {
            throw new HttpException(422, 'Message body cannot be empty.');
        }

        if (mb_strlen($body) > self::MAX_BODY_LENGTH) {
            throw new HttpException(422, 'Message body exceeds the maximum allowed length of ' . self::MAX_BODY_LENGTH . ' characters.');
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id'       => $sender->id,
            'body'            => $body,
            'read_at'         => null,
        ]);

        // Touch the conversation's updated_at so list ordering stays correct.
        $conversation->touch();

        return $message;
    }

    /**
     * Mark all unread messages from the OTHER participant as read.
     *
     * Safe to call repeatedly (idempotent).
     * Never touches messages sent by the reader themselves.
     *
     * @throws HttpException (403) if user is not a participant
     */
    public function markAsRead(Conversation $conversation, User $reader): void
    {
        if (! $conversation->hasParticipant($reader)) {
            throw new HttpException(403, 'You are not a participant in this conversation.');
        }

        // Mark only messages sent by the OTHER participant that are still unread.
        Message::where('conversation_id', $conversation->id)
            ->where('sender_id', '!=', $reader->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Determine whether user A and user B have an accepted ConnectionRequest
     * in either direction (mirrors the query used in ConnectionRequestController@index).
     */
    public function areConnected(User $userA, User $userB): bool
    {
        return ConnectionRequest::where('status', ConnectionStatus::Accepted->value)
            ->where(function ($query) use ($userA, $userB) {
                $query->where(function ($inner) use ($userA, $userB) {
                    $inner->where('requester_id', $userA->id)
                          ->where('recipient_id', $userB->id);
                })->orWhere(function ($inner) use ($userA, $userB) {
                    $inner->where('requester_id', $userB->id)
                          ->where('recipient_id', $userA->id);
                });
            })
            ->exists();
    }

    /**
     * Return [min(a,b), max(a,b)] for canonical conversation storage.
     *
     * @return array{int, int}
     */
    private function canonicalPair(int $a, int $b): array
    {
        return [$a < $b ? $a : $b, $a < $b ? $b : $a];
    }
}
