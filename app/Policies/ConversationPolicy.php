<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

/**
 * Authorization policy for Conversation actions.
 *
 * Convention mirrors ConnectionRequestPolicy:
 *   - Policy returns false to produce a 403 Forbidden.
 *   - Business-rule conflicts (no accepted connection, etc.)
 *     are thrown as HttpException(409) in ConversationService.
 *
 * All actions require the user to be a participant (requester or recipient)
 * of the specific conversation.
 */
class ConversationPolicy
{
    /**
     * View the conversation list — any authenticated user may access their own list.
     * The query in the controller is scoped to the authenticated user,
     * so this gate just ensures auth:sanctum is satisfied (returns true).
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * View a specific conversation (and its messages).
     * Only participants may view.
     */
    public function view(User $user, Conversation $conversation): bool
    {
        return $conversation->hasParticipant($user);
    }

    /**
     * Send a message in a conversation.
     * Only participants may send.
     */
    public function sendMessage(User $user, Conversation $conversation): bool
    {
        return $conversation->hasParticipant($user);
    }

    /**
     * Mark messages in a conversation as read.
     * Only participants may mark read.
     */
    public function markAsRead(User $user, Conversation $conversation): bool
    {
        return $conversation->hasParticipant($user);
    }
}
