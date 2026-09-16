<?php

namespace Database\Factories;

use App\Enums\ConnectionStatus;
use App\Models\ConnectionRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConnectionRequest>
 */
class ConnectionRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Defaults to a pending request between two distinct factory-created users.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'requester_id' => User::factory(),
            'recipient_id' => User::factory(),
            'status'       => ConnectionStatus::Pending->value,
        ];
    }

    // ── State methods ──────────────────────────────────────────────────────────

    /**
     * Create an accepted connection request.
     */
    public function accepted(): static
    {
        return $this->state(fn () => ['status' => ConnectionStatus::Accepted->value]);
    }

    /**
     * Create a rejected connection request.
     */
    public function rejected(): static
    {
        return $this->state(fn () => ['status' => ConnectionStatus::Rejected->value]);
    }

    /**
     * Create a cancelled connection request.
     */
    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => ConnectionStatus::Cancelled->value]);
    }
}
