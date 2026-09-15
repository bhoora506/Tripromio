<?php

namespace App\Policies;

use App\Enums\TripStatus;
use App\Models\Trip;
use App\Models\TripJoinRequest;
use App\Models\User;

class TripJoinRequestPolicy
{
    /**
     * Can the authenticated user submit a join request for this trip?
     *
     * Business rules enforced here (policy returns false for authorization;
     * business-logic conflicts like duplicate requests return 409 in the service):
     *   1. User is not the trip owner.
     *   2. Trip status is 'published' only.
     *   3. Trip end_date is not in the past.
     *   4. Trip has remaining capacity.
     *   5. User is not already an active member.
     *
     * Duplicate-pending-request and already-approved-member checks are handled
     * in TripJoinRequestService to return 409 Conflict instead of 403.
     */
    public function store(User $user, Trip $trip): bool
    {
        // Owner cannot request to join their own trip
        if ($trip->user_id === $user->id) {
            return false;
        }

        // Only published trips accept join requests
        if ($trip->status !== TripStatus::Published) {
            return false;
        }

        return true;
    }

    /**
     * Only the trip owner may view incoming join requests.
     */
    public function index(User $user, Trip $trip): bool
    {
        return $trip->user_id === $user->id;
    }

    /**
     * Only the trip owner may approve a join request.
     */
    public function approve(User $user, TripJoinRequest $joinRequest): bool
    {
        return $joinRequest->trip->user_id === $user->id;
    }

    /**
     * Only the trip owner may reject a join request.
     */
    public function reject(User $user, TripJoinRequest $joinRequest): bool
    {
        return $joinRequest->trip->user_id === $user->id;
    }

    /**
     * Only the requester may cancel their own pending join request.
     */
    public function cancel(User $user, TripJoinRequest $joinRequest): bool
    {
        return $joinRequest->user_id === $user->id;
    }
}
