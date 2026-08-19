<?php

namespace Database\Factories;

use App\Enums\TripStatus;
use App\Enums\TripType;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Trip>
 */
class TripFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Guarantees:
     * - end_date >= start_date (same day trips are valid)
     * - budget_max >= budget_min when both are set
     * - status defaults to draft (the natural initial state)
     * - max_members is between 2 and 10 (inclusive)
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = $this->faker->dateTimeBetween('+1 week', '+3 months');
        $endDate   = $this->faker->dateTimeBetween($startDate, '+4 months');

        $budgetMin = $this->faker->numberBetween(2000, 20000);
        $budgetMax = $this->faker->numberBetween($budgetMin, $budgetMin + 30000);

        $destinations = [
            'Manali', 'Goa', 'Udaipur', 'Jaipur', 'Rishikesh',
            'Leh', 'Coorg', 'Munnar', 'Darjeeling', 'Varanasi',
        ];

        return [
            'user_id'     => User::factory(),
            'title'       => $this->faker->sentence(4, true),
            'destination' => $this->faker->randomElement($destinations),
            'place_id'    => null,
            'latitude'    => null,
            'longitude'   => null,
            'start_date'  => $startDate->format('Y-m-d'),
            'end_date'    => $endDate->format('Y-m-d'),
            'budget_min'  => $budgetMin,
            'budget_max'  => $budgetMax,
            'trip_type'   => $this->faker->randomElement(TripType::values()),
            'description' => $this->faker->optional(0.7)->paragraph(),
            'max_members' => $this->faker->numberBetween(2, 10),
            'status'      => TripStatus::Draft->value,
        ];
    }

    // ── State methods ──────────────────────────────────────────────────────────

    /**
     * Create a published trip.
     */
    public function published(): static
    {
        return $this->state(fn () => ['status' => TripStatus::Published->value]);
    }

    /**
     * Create an ongoing trip (dates straddling now).
     */
    public function ongoing(): static
    {
        return $this->state(function () {
            $startDate = $this->faker->dateTimeBetween('-1 week', 'now');
            $endDate   = $this->faker->dateTimeBetween('now', '+2 weeks');

            return [
                'status'     => TripStatus::Ongoing->value,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date'   => $endDate->format('Y-m-d'),
            ];
        });
    }

    /**
     * Create a completed trip (dates in the past).
     */
    public function completed(): static
    {
        return $this->state(function () {
            $startDate = $this->faker->dateTimeBetween('-2 months', '-3 weeks');
            $endDate   = $this->faker->dateTimeBetween($startDate, '-1 week');

            return [
                'status'     => TripStatus::Completed->value,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date'   => $endDate->format('Y-m-d'),
            ];
        });
    }

    /**
     * Create a cancelled trip.
     */
    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => TripStatus::Cancelled->value]);
    }

    /**
     * Trip with no budget set (budget_min/max nullable by design).
     */
    public function withoutBudget(): static
    {
        return $this->state(fn () => ['budget_min' => null, 'budget_max' => null]);
    }
}
