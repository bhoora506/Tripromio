<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Conversation model.
 *
 * Represents a 1-to-1 messaging thread between two connected users.
 *
 * Canonical ordering:
 * ───────────────────
 * requester_id always holds the lower user ID and recipient_id the higher.
 * This is enforced in ConversationService::findOrCreate so that the
 * UNIQUE(requester_id, recipient_id) DB constraint reliably prevents
 * duplicate conversations for the same pair regardless of which user initiates.
 *
 * Both requester_id and recipient_id are simply "participants" — the ordering
 * carries no semantic meaning about who started the conversation.
 *
 * Business rule:
 * ──────────────
 * A conversation may only exist between users who have an accepted
 * ConnectionRequest in either direction. This is enforced in
 * ConversationService, not at the DB level.
 */
#[Fillable(['requester_id', 'recipient_id'])]
class Conversation extends Model
{
    use HasFactory;

    // ── Relationships ──────────────────────────────────────────────────────────

    /**
     * Participant stored in the requester_id column (lower user ID).
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /**
     * Participant stored in the recipient_id column (higher user ID).
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    /**
     * All messages in this conversation, oldest first by default.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at');
    }

    /**
     * The single most recent message in this conversation.
     * Useful for conversation list previews — avoids loading all messages.
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany('created_at');
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * Determine whether the given user is a participant in this conversation.
     */
    public function hasParticipant(User $user): bool
    {
        return $this->requester_id === $user->id
            || $this->recipient_id === $user->id;
    }

    /**
     * Return the other participant from the perspective of the given user.
     * Assumes the given user is already a participant.
     */
    public function otherParticipant(User $user): User
    {
        if ($this->requester_id === $user->id) {
            return $this->recipient;
        }

        return $this->requester;
    }
}
