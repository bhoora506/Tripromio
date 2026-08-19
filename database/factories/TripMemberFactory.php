<?php

namespace Database\Factories;

use App\Enums\MemberRole;
use App\Enums\MemberStatus;
use App\Models\Trip;
use App\Models\TripMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TripMember>
 */
class TripMemberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Defaults to a regular (non-owner) active member.
     * Use the owner() state method for owner rows.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'trip_id'   => Trip::factory(),
            'user_id'   => User::factory(),
            'role'      => MemberRole::Member->value,
            'status'    => MemberStatus::Active->value,
            'joined_at' => now(),
        ];
    }

    // ── State methods ──────────────────────────────────────────────────────────

    /**
     * Create an owner membership row.
     * joined_at is null for owner rows because the owner doesn't "join" — they create.
     */
    public function owner(): static
    {
        return $this->state(fn () => [
            'role'      => MemberRole::Owner->value,
            'joined_at' => null,
        ]);
    }

    /**
     * Create a membership that has left.
     */
    public function left(): static
    {
        return $this->state(fn () => ['status' => MemberStatus::Left->value]);
    }

    /**
     * Create a membership that was removed by the owner.
     */
    public function removed(): static
    {
        return $this->state(fn () => ['status' => MemberStatus::Removed->value]);
    }
}
