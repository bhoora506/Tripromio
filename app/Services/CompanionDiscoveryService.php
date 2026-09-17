<?php

namespace App\Services;

use App\Enums\TravelStyle;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Companion Discovery Service.
 *
 * Mirrors TripDiscoveryService conventions:
 *   - all query/filtering logic lives here
 *   - the controller is thin
 *   - filters are applied conditionally via ->when()
 *   - result is a paginator
 *
 * Visibility rules
 * ────────────────
 * Only users with is_discoverable = true are returned.
 * The authenticated user is always excluded.
 * Users without a profile row are excluded.
 *
 * Profile completion gate (MVP)
 * ──────────────────────────────
 * ProfileCompletionService::calculate() is a PHP method using loaded
 * relationships — it cannot be used as a WHERE clause without N+1 or a
 * raw subquery.
 *
 * The MVP gate approximates a meaningful profile by requiring at least one
 * of: bio, city, country, or travel_style to be filled in on the profile.
 * This maps to a minimum contribution of 10–15 completion points, eliminating
 * completely empty/ghost profiles from the feed.
 *
 * This is documented as an MVP approximation. A future migration can add
 * a denormalised profile_completion column to user_profiles to support
 * precise DB-level threshold filtering.
 *
 * Sort
 * ────
 * profile_completion (default): orders by the number of non-null core fields
 * (using a CASE expression), then id DESC as deterministic tie-breaker.
 *
 * newest: users.created_at DESC, users.id DESC.
 *
 * N+1 prevention
 * ──────────────
 * Eager loads: profile, interests, preferredDestinations.
 * Date filter uses whereHas() at DB level — no availability records are loaded.
 * Destination/place_id filters use whereHas() at DB level.
 * Interest filter uses whereHas() at DB level.
 */
class CompanionDiscoveryService
{
    /**
     * Execute the companion discovery query and return a paginator.
     *
     * @param  int  $authenticatedUserId
     * @param  array<string, mixed>  $filters  validated values from CompanionDiscoveryRequest
     */
    public function discover(int $authenticatedUserId, array $filters): LengthAwarePaginator
    {
        $perPage = min((int) ($filters['per_page'] ?? 20), 50);

        $query = User::query()
            // Eager load everything CompanionResource needs — single query each
            ->with(['profile', 'interests', 'preferredDestinations'])
            // ── Base visibility: exclude self
            ->where('users.id', '!=', $authenticatedUserId)
            // ── Require a discoverable profile
            ->whereHas('profile', fn (Builder $q) => $q
                ->where('is_discoverable', true)
                // Profile completion gate: at least one core field must be set
                // This excludes ghost/empty profiles with 0% completion
                ->where(fn (Builder $inner) => $inner
                    ->whereNotNull('bio')
                    ->orWhereNotNull('city')
                    ->orWhereNotNull('country')
                    ->orWhereNotNull('travel_style')
                )
            );

        // ── Filter: destination (text search against preferred_destinations.destination)
        $query->when(
            ! empty($filters['destination']),
            fn (Builder $q) => $q->whereHas('preferredDestinations', fn (Builder $inner) =>
                $inner->where('destination', 'like', '%' . $filters['destination'] . '%')
            )
        );

        // ── Filter: place_id (exact match against preferred_destinations.place_id)
        $query->when(
            ! empty($filters['place_id']),
            fn (Builder $q) => $q->whereHas('preferredDestinations', fn (Builder $inner) =>
                $inner->where('place_id', $filters['place_id'])
            )
        );

        // ── Filter: date overlap with travel_availabilities
        // A companion qualifies when ANY of their availability windows overlaps
        // the requested period:
        //   availability.start_date <= requested_end_date
        //   AND availability.end_date >= requested_start_date
        //
        // If only one boundary is supplied, the half-open filter still applies.
        $hasDateFilter = ! empty($filters['start_date']) || ! empty($filters['end_date']);

        $query->when(
            $hasDateFilter,
            fn (Builder $q) => $q->whereHas('travelAvailabilities', function (Builder $inner) use ($filters) {
                if (! empty($filters['end_date'])) {
                    // companion start must be <= our end
                    $inner->where('start_date', '<=', $filters['end_date']);
                }
                if (! empty($filters['start_date'])) {
                    // companion end must be >= our start
                    $inner->where('end_date', '>=', $filters['start_date']);
                }
            })
        );

        // ── Filter: travel_style (exact enum match on user_profiles.travel_style)
        $query->when(
            ! empty($filters['travel_style']),
            fn (Builder $q) => $q->whereHas('profile', fn (Builder $inner) =>
                $inner->where('travel_style', $filters['travel_style'])
            )
        );

        // ── Filter: interest_ids — companion must share at least one interest
        $query->when(
            ! empty($filters['interest_ids']) && is_array($filters['interest_ids']),
            fn (Builder $q) => $q->whereHas('interests', fn (Builder $inner) =>
                $inner->whereIn('interests.id', $filters['interest_ids'])
            )
        );

        // ── Sorting
        $sort = $filters['sort'] ?? 'profile_completion';

        match ($sort) {
            'newest' => $query
                ->orderBy('users.created_at', 'desc')
                ->orderBy('users.id', 'desc'),

            // profile_completion (default): approximate via a correlated subquery
            // computing weighted score from user_profiles columns.
            // Weights match ProfileCompletionService::calculate() (photo omitted — no path in tests):
            //   photo_path(20) + bio(15) + city(15) + country(10) + languages(15) + travel_style(10)
            // Using a correlated subquery avoids the JOIN-caused ambiguous column issue
            // in Laravel paginator's COUNT(*) query.
            default => $query
                ->orderByRaw('
                    (SELECT
                        CASE WHEN up.profile_photo_path IS NOT NULL THEN 20 ELSE 0 END +
                        CASE WHEN up.bio               IS NOT NULL THEN 15 ELSE 0 END +
                        CASE WHEN up.city              IS NOT NULL THEN 15 ELSE 0 END +
                        CASE WHEN up.country           IS NOT NULL THEN 10 ELSE 0 END +
                        CASE WHEN up.languages         IS NOT NULL THEN 15 ELSE 0 END +
                        CASE WHEN up.travel_style      IS NOT NULL THEN 10 ELSE 0 END
                     FROM user_profiles up
                     WHERE up.user_id = users.id
                    ) DESC
                ')
                ->orderBy('users.id', 'desc'),
        };

        return $query->paginate($perPage);
    }
}
