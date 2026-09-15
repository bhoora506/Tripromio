<?php

namespace Database\Factories;

use App\Enums\JoinRequestStatus;
use App\Models\Trip;
use App\Models\TripJoinRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TripJoinRequest>
 */
class TripJoinRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Defaults to a pending request.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'trip_id' => Trip::factory(),
            'user_id' => User::factory(),
            'status'  => JoinRequestStatus::Pending->value,
        ];
    }

    // ── State methods ──────────────────────────────────────────────────────────

    /**
     * Create an approved join request.
     */
    public function approved(): static
    {
        return $this->state(fn () => ['status' => JoinRequestStatus::Approved->value]);
    }

    /**
     * Create a rejected join request.
     */
    public function rejected(): static
    {
        return $this->state(fn () => ['status' => JoinRequestStatus::Rejected->value]);
    }

    /**
     * Create a cancelled join request.
     */
    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => JoinRequestStatus::Cancelled->value]);
    }
}
