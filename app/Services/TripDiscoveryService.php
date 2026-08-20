<?php

namespace App\Services;

use App\Enums\TripStatus;
use App\Http\Requests\Trip\TripDiscoveryRequest;
use App\Models\Trip;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Handles the trip discovery query logic.
 *
 * Visibility rules
 * ────────────────
 * Only `published` trips are discoverable.
 * The authenticated user's own trips are excluded by default.
 * Trips whose end_date is in the past are excluded (upcoming rule).
 *
 * Date overlap rule
 * ─────────────────
 * A trip overlaps the requested date window if:
 *   trip.start_date <= requested_end  AND  trip.end_date >= requested_start
 * This correctly handles all overlap cases including partial overlaps.
 *
 * Budget overlap rule
 * ───────────────────
 * A trip is budget-compatible if its range overlaps the requested range.
 * Trips with NULL budgets are always included (budget unspecified = open to all).
 * When only budget_min is requested: trips where trip_max >= requested_min (or trip_max is null).
 * When only budget_max is requested: trips where trip_min <= requested_max (or trip_min is null).
 * When both are requested: standard range overlap.
 *
 * Sorting
 * ───────
 * start_date ASC (default) — surfaces upcoming trips first.
 * newest     → created_at DESC
 * updated    → updated_at DESC
 * All sorts use `id ASC` as a stable secondary sort to prevent pagination drift
 * when the primary sort column has duplicate values.
 *
 * Upcoming rule
 * ─────────────
 * Only trips whose end_date >= today are returned.
 * This ensures completed/past trips are excluded even if still `published`.
 * Ongoing trips (end_date in future) are included.
 */
class TripDiscoveryService
{
    /**
     * Execute the discovery query and return a paginator.
     *
     * @param  int  $authenticatedUserId
     * @param  array<string, mixed>  $filters  (from TripDiscoveryRequest::validated())
     */
    public function discover(int $authenticatedUserId, array $filters): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 20);

        $query = Trip::query()
            ->with('owner', 'interests')
            ->withCount(['tripMembers as active_members_count' => fn ($q) => $q->where('status', 'active')])
            // ── Base visibility rules ──────────────────────────────────────
            ->where('status', TripStatus::Published->value)
            ->where('user_id', '!=', $authenticatedUserId)
            // ── Upcoming rule: exclude past trips ─────────────────────────
            ->where('end_date', '>=', today());

        // ── Filters ───────────────────────────────────────────────────────

        $query->when(
            ! empty($filters['destination']),
            fn (Builder $q) => $q->where('destination', 'like', '%' . $filters['destination'] . '%')
        );

        $query->when(
            ! empty($filters['trip_type']),
            fn (Builder $q) => $q->where('trip_type', $filters['trip_type'])
        );

        // Date overlap: trip overlaps requested window when:
        //   trip.start_date <= requested_end  AND  trip.end_date >= requested_start
        $query->when(
            ! empty($filters['start_date']),
            fn (Builder $q) => $q->where('end_date', '>=', $filters['start_date'])
        );

        $query->when(
            ! empty($filters['end_date']),
            fn (Builder $q) => $q->where('start_date', '<=', $filters['end_date'])
        );

        // Budget overlap: trips with NULL budgets are always included (unspecified = open).
        // Requested budget_min: trip must have max budget >= requested_min (or no max set).
        $query->when(
            isset($filters['budget_min']) && $filters['budget_min'] !== null,
            fn (Builder $q) => $q->where(fn (Builder $inner) => $inner
                ->whereNull('budget_max')
                ->orWhere('budget_max', '>=', $filters['budget_min'])
            )
        );

        // Requested budget_max: trip must have min budget <= requested_max (or no min set).
        $query->when(
            isset($filters['budget_max']) && $filters['budget_max'] !== null,
            fn (Builder $q) => $q->where(fn (Builder $inner) => $inner
                ->whereNull('budget_min')
                ->orWhere('budget_min', '<=', $filters['budget_max'])
            )
        );

        // ── Sorting ───────────────────────────────────────────────────────

        $sort = $filters['sort'] ?? 'start_date';

        match ($sort) {
            'newest'     => $query->orderBy('created_at', 'desc')->orderBy('id', 'asc'),
            'updated'    => $query->orderBy('updated_at', 'desc')->orderBy('id', 'asc'),
            default      => $query->orderBy('start_date', 'asc')->orderBy('id', 'asc'),
        };

        return $query->paginate($perPage);
    }
}
