<?php

namespace App\Models;

use App\Enums\ConnectionStatus;
use Database\Factories\ConnectionRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ConnectionRequest model.
 *
 * Represents a user's request to connect with another user.
 *
 * Workflow
 * ────────
 * pending   → accepted  (recipient accepts)
 * pending   → rejected  (recipient rejects)
 * pending   → cancelled (requester cancels)
 *
 * A user may submit a fresh pending request after a previous rejected or
 * cancelled request. Only one pending request per (requester_id, recipient_id)
 * pair is allowed at a time — enforced at the application layer (F4).
 *
 * Self-request prevention:
 * requester_id != recipient_id is enforced at the application layer.
 * A portable DB-level CHECK is not used because the project targets both
 * MySQL (production) and SQLite (tests); raw cross-column CHECK syntax differs.
 *
 * Do NOT add connection acceptance business logic here.
 * Business workflow belongs in ConnectionRequestService (Phase F4).
 */
#[Fillable(['requester_id', 'recipient_id', 'status'])]
class ConnectionRequest extends Model
{
    /** @use HasFactory<ConnectionRequestFactory> */
    use HasFactory;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status' => ConnectionStatus::class,
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────────

    /**
     * The user who sent this connection request.
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /**
     * The user who received this connection request.
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === ConnectionStatus::Pending;
    }

    public function isAccepted(): bool
    {
        return $this->status === ConnectionStatus::Accepted;
    }

    public function isRejected(): bool
    {
        return $this->status === ConnectionStatus::Rejected;
    }

    public function isCancelled(): bool
    {
        return $this->status === ConnectionStatus::Cancelled;
    }
}
