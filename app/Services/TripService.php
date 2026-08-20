<?php

namespace App\Services;

use App\Enums\MemberRole;
use App\Enums\MemberStatus;
use App\Enums\TripStatus;
use App\Models\Trip;
use App\Models\TripMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TripService
{
    /**
     * Create a new trip and automatically add the creator as the owner member.
     * Wrapped in a transaction — if either step fails, both are rolled back.
     *
     * @param  array<string, mixed>  $data
     */
    public function createTrip(User $owner, array $data): Trip
    {
        return DB::transaction(function () use ($owner, $data) {
            $interestIds = $data['interest_ids'] ?? [];
            // Remove non-model fields before creating the Trip record
            unset($data['interest_ids']);

            $trip = Trip::create([
                ...$data,
                'user_id' => $owner->id,
                'status'  => TripStatus::Draft->value,
            ]);

            TripMember::create([
                'trip_id'   => $trip->id,
                'user_id'   => $owner->id,
                'role'      => MemberRole::Owner->value,
                'status'    => MemberStatus::Active->value,
                'joined_at' => null, // owner creates, not joins
            ]);

            // Sync trip interests if provided
            if (! empty($interestIds)) {
                $trip->interests()->sync(array_unique($interestIds));
            }

            return $trip;
        });
    }

    /**
     * Update mutable fields on a trip.
     *
     * Lifecycle rules:
     *   draft      — all editable fields allowed
     *   published  — all editable fields allowed (owner may still refine details)
     *   ongoing    — only description and title may change
     *   completed  — immutable
     *   cancelled  — immutable
     *
     * @param  array<string, mixed>  $data
     */
    public function updateTrip(Trip $trip, array $data): Trip
    {
        $interestIds = array_key_exists('interest_ids', $data) ? $data['interest_ids'] : false;
        unset($data['interest_ids']);

        match ($trip->status) {
            TripStatus::Completed,
            TripStatus::Cancelled => throw new \Symfony\Component\HttpKernel\Exception\HttpException(
                409, 'A ' . $trip->status->value . ' trip cannot be updated.'
            ),
            TripStatus::Ongoing => $data = array_intersect_key($data, array_flip(['title', 'description'])),
            default => null,
        };

        $trip->update($data);

        // Sync interests only when the key was explicitly present in the request
        if ($interestIds !== false) {
            $trip->interests()->sync(array_unique((array) $interestIds));
        }

        return $trip->fresh();
    }

    /**
     * Transition a trip from draft → published.
     * Validates that the trip has the minimum required information.
     */
    public function publishTrip(Trip $trip): Trip
    {
        if (! $trip->status->canTransitionTo(TripStatus::Published)) {
            throw new \Symfony\Component\HttpKernel\Exception\HttpException(
                409,
                "Cannot publish a trip that is {$trip->status->value}."
            );
        }

        // Minimum publish requirements
        $missing = [];
        if (empty($trip->title))        { $missing[] = 'title'; }
        if (empty($trip->destination))  { $missing[] = 'destination'; }
        if (empty($trip->start_date))   { $missing[] = 'start_date'; }
        if (empty($trip->end_date))     { $missing[] = 'end_date'; }
        if (empty($trip->trip_type))    { $missing[] = 'trip_type'; }
        if (empty($trip->max_members))  { $missing[] = 'max_members'; }

        if (! empty($missing)) {
            throw new \Symfony\Component\HttpKernel\Exception\HttpException(
                422,
                'Trip cannot be published. Missing required fields: ' . implode(', ', $missing) . '.'
            );
        }

        $trip->update(['status' => TripStatus::Published->value]);

        return $trip->fresh();
    }

    /**
     * Cancel a trip from any non-terminal state.
     */
    public function cancelTrip(Trip $trip): Trip
    {
        if (! $trip->status->canTransitionTo(TripStatus::Cancelled)) {
            throw new \Symfony\Component\HttpKernel\Exception\HttpException(
                409,
                "Cannot cancel a trip that is already {$trip->status->value}."
            );
        }

        $trip->update(['status' => TripStatus::Cancelled->value]);

        return $trip->fresh();
    }
}
