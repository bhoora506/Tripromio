<?php

namespace App\Services;

use App\Enums\JoinRequestStatus;
use App\Enums\MemberRole;
use App\Enums\MemberStatus;
use App\Enums\TripStatus;
use App\Models\Trip;
use App\Models\TripJoinRequest;
use App\Models\TripMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TripJoinRequestService
{
    /**
     * Submit a join request for a trip.
     *
     * Business rules checked (policy handles owner/status; here we check
     * capacity, duplicate-pending, already-member, and past-trip):
     *
     *   1. Trip end_date must not be in the past.
     *   2. User must not already be an active member.
     *   3. User must not already have a pending request for this trip.
     *   4. Trip must have remaining capacity.
     *
     * A user MAY submit a fresh request after a previous rejected/cancelled one.
     *
     * @throws HttpException (409)
     */
    public function createRequest(User $user, Trip $trip): TripJoinRequest
    {
        // Rule: trip must not be in the past
        if ($trip->end_date->isPast()) {
            throw new HttpException(409, 'This trip has already ended and is no longer accepting join requests.');
        }

        // Rule: user must not already be an active member
        $isMember = $trip->tripMembers()
            ->where('user_id', $user->id)
            ->where('status', MemberStatus::Active->value)
            ->exists();

        if ($isMember) {
            throw new HttpException(409, 'You are already a member of this trip.');
        }

        // Rule: no duplicate pending request
        $hasPending = $trip->joinRequests()
            ->where('user_id', $user->id)
            ->where('status', JoinRequestStatus::Pending->value)
            ->exists();

        if ($hasPending) {
            throw new HttpException(409, 'You already have a pending join request for this trip.');
        }

        // Rule: trip must have remaining capacity
        if (! $trip->hasOpenSlots()) {
            throw new HttpException(409, 'This trip is full and is not accepting more members.');
        }

        return TripJoinRequest::create([
            'trip_id' => $trip->id,
            'user_id' => $user->id,
            'status'  => JoinRequestStatus::Pending->value,
        ]);
    }

    /**
     * Approve a pending join request.
     *
     * Uses a DB transaction with a pessimistic lock on the trip row to prevent
     * concurrent approvals from exceeding max_members.
     *
     * Steps (inside transaction):
     *   1. Lock the trip row for update.
     *   2. Re-validate the request is still pending.
     *   3. Re-check trip is still published (not cancelled, etc.).
     *   4. Re-check remaining capacity.
     *   5. Create TripMember row.
     *   6. Mark request approved.
     *
     * @throws HttpException (409)
     */
    public function approve(TripJoinRequest $joinRequest): TripJoinRequest
    {
        return DB::transaction(function () use ($joinRequest) {
            // Pessimistic lock on the trip row to prevent race conditions
            $trip = Trip::where('id', $joinRequest->trip_id)->lockForUpdate()->firstOrFail();

            // Request must still be pending
            $joinRequest->refresh();
            if (! $joinRequest->isPending()) {
                throw new HttpException(
                    409,
                    "Cannot approve a request that is already {$joinRequest->status->value}."
                );
            }

            // Trip must still be published
            if ($trip->status !== TripStatus::Published) {
                throw new HttpException(
                    409,
                    "Cannot approve a request — trip is {$trip->status->value}."
                );
            }

            // Re-check capacity with locked trip data
            if (! $trip->hasOpenSlots()) {
                throw new HttpException(409, 'This trip is full. No more members can be approved.');
            }

            // Create confirmed membership
            TripMember::create([
                'trip_id'   => $trip->id,
                'user_id'   => $joinRequest->user_id,
                'role'      => MemberRole::Member->value,
                'status'    => MemberStatus::Active->value,
                'joined_at' => now(),
            ]);

            // Mark request approved
            $joinRequest->update(['status' => JoinRequestStatus::Approved->value]);

            return $joinRequest->refresh();
        });
    }

    /**
     * Reject a pending join request (trip owner only).
     *
     * Only pending requests may be rejected.
     * Does NOT create a TripMember row.
     *
     * @throws HttpException (409)
     */
    public function reject(TripJoinRequest $joinRequest): TripJoinRequest
    {
        if (! $joinRequest->isPending()) {
            throw new HttpException(
                409,
                "Cannot reject a request that is already {$joinRequest->status->value}."
            );
        }

        $joinRequest->update(['status' => JoinRequestStatus::Rejected->value]);

        return $joinRequest->refresh();
    }

    /**
     * Cancel a pending join request (requester only).
     *
     * Only pending requests may be cancelled.
     * Does NOT create a TripMember row.
     *
     * @throws HttpException (409)
     */
    public function cancel(TripJoinRequest $joinRequest): TripJoinRequest
    {
        if (! $joinRequest->isPending()) {
            throw new HttpException(
                409,
                "Cannot cancel a request that is already {$joinRequest->status->value}."
            );
        }

        $joinRequest->update(['status' => JoinRequestStatus::Cancelled->value]);

        return $joinRequest->refresh();
    }
}
