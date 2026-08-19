<?php

namespace App\Models;

use App\Enums\MemberRole;
use App\Enums\MemberStatus;
use App\Enums\TripStatus;
use App\Enums\TripType;
use Database\Factories\TripFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * Trip model.
 *
 * max_members semantics
 * ─────────────────────
 * max_members = total number of travellers in the trip, INCLUDING the owner.
 * Example: max_members = 4 → owner + 3 additional members.
 *
 * Ownership
 * ─────────
 * trips.user_id identifies the trip creator/owner.
 * The owner also has a corresponding trip_members row with role = 'owner'.
 * The trip_members row is the authoritative source for membership;
 * trips.user_id is the ownership reference used for authorization.
 *
 * Foreign-key deletion behavior
 * ────────────────────────────────
 * trips.user_id → RESTRICT (not cascade): trip history is preserved when
 * an account is deactivated. Account-deletion handling is explicit in Phase 7+.
 */
#[Fillable([
    'user_id',
    'title',
    'destination',
    'place_id',
    'latitude',
    'longitude',
    'start_date',
    'end_date',
    'budget_min',
    'budget_max',
    'trip_type',
    'description',
    'max_members',
    'status',
])]
class Trip extends Model
{
    /** @use HasFactory<TripFactory> */
    use HasFactory;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'start_date'  => 'date',
            'end_date'    => 'date',
            'budget_min'  => 'decimal:2',
            'budget_max'  => 'decimal:2',
            'latitude'    => 'decimal:7',
            'longitude'   => 'decimal:7',
            'trip_type'   => TripType::class,
            'status'      => TripStatus::class,
            'max_members' => 'integer',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────────

    /**
     * The user who created and owns this trip.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * All TripMember rows for this trip (includes owner + members, all statuses).
     */
    public function tripMembers(): HasMany
    {
        return $this->hasMany(TripMember::class);
    }

    /**
     * Active members of this trip (both owner and members with status=active).
     */
    public function activeMembers(): HasMany
    {
        return $this->hasMany(TripMember::class)
            ->where('status', MemberStatus::Active->value);
    }

    /**
     * Users who are active members of this trip (convenient many-to-many view).
     */
    public function members(): HasManyThrough
    {
        return $this->hasManyThrough(
            User::class,
            TripMember::class,
            'trip_id',   // FK on trip_members
            'id',        // FK on users
            'id',        // local key on trips
            'user_id',   // local key on trip_members
        );
    }

    // ── Computed helpers ───────────────────────────────────────────────────────

    /**
     * Returns the number of currently active member slots remaining.
     * (max_members includes the owner, so we subtract active member count.)
     */
    public function remainingSlots(): int
    {
        return max(0, $this->max_members - $this->activeMembers()->count());
    }

    /**
     * Returns true if the trip can accept more members.
     */
    public function hasOpenSlots(): bool
    {
        return $this->remainingSlots() > 0;
    }
}
