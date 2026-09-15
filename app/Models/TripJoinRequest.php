<?php

namespace App\Models;

use App\Enums\JoinRequestStatus;
use Database\Factories\TripJoinRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TripJoinRequest model.
 *
 * Represents a user's request to join a published trip.
 *
 * Workflow
 * ────────
 * pending   → approved  (owner approves; trip_members row created atomically)
 * pending   → rejected  (owner rejects)
 * pending   → cancelled (requester cancels)
 *
 * A user may submit a fresh pending request after a previous rejected or
 * cancelled request. Only one pending request per (trip_id, user_id) pair
 * is allowed at a time — enforced at the application layer.
 *
 * Do NOT use this model for confirmed membership state.
 * Confirmed membership lives in TripMember.
 */
#[Fillable(['trip_id', 'user_id', 'status'])]
class TripJoinRequest extends Model
{
    /** @use HasFactory<TripJoinRequestFactory> */
    use HasFactory;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status' => JoinRequestStatus::class,
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────────

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    /**
     * The user who submitted this join request.
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === JoinRequestStatus::Pending;
    }

    public function isApproved(): bool
    {
        return $this->status === JoinRequestStatus::Approved;
    }

    public function isRejected(): bool
    {
        return $this->status === JoinRequestStatus::Rejected;
    }

    public function isCancelled(): bool
    {
        return $this->status === JoinRequestStatus::Cancelled;
    }
}
