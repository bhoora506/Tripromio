<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Message model.
 *
 * Represents a single text message within a Conversation.
 *
 * Read state:
 * ───────────
 * read_at is null until the OTHER participant reads the conversation.
 * Own messages are never touched by the markAsRead operation.
 *
 * Body:
 * ─────
 * Plain text only. Maximum length enforced at the application layer
 * (ConversationService::sendMessage). No HTML or markdown for MVP.
 */
#[Fillable(['conversation_id', 'sender_id', 'body', 'read_at'])]
class Message extends Model
{
    use HasFactory;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────────

    /**
     * The conversation this message belongs to.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * The user who sent this message.
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * Whether this message has been read by the recipient.
     */
    public function isRead(): bool
    {
        return $this->read_at !== null;
    }
}
