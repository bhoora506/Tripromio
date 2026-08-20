<?php

namespace Database\Factories;

use App\Models\TravelAvailability;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TravelAvailability>
 */
class TravelAvailabilityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Generates a valid future availability window.
     * start_date < end_date is always guaranteed.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = $this->faker->dateTimeBetween('now', '+3 months');
        $end   = $this->faker->dateTimeBetween($start, '+4 months');

        return [
            'user_id'    => User::factory(),
            'start_date' => $start->format('Y-m-d'),
            'end_date'   => $end->format('Y-m-d'),
        ];
    }

    /**
     * Past availability window (ended before today).
     */
    public function past(): static
    {
        return $this->state(function () {
            $start = $this->faker->dateTimeBetween('-3 months', '-2 months');
            $end   = $this->faker->dateTimeBetween($start, '-1 month');

            return [
                'start_date' => $start->format('Y-m-d'),
                'end_date'   => $end->format('Y-m-d'),
            ];
        });
    }

    /**
     * Ongoing availability window (started before today, ends in future).
     */
    public function ongoing(): static
    {
        return $this->state(function () {
            $start = $this->faker->dateTimeBetween('-2 weeks', 'now');
            $end   = $this->faker->dateTimeBetween('tomorrow', '+2 months');

            return [
                'start_date' => $start->format('Y-m-d'),
                'end_date'   => $end->format('Y-m-d'),
            ];
        });
    }
}
