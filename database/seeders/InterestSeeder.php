<?php

namespace Database\Seeders;

use App\Models\Interest;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class InterestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $interests = [
            'Trekking',
            'Backpacking',
            'Photography',
            'Adventure',
            'Camping',
            'Nature',
            'Road Trip',
            'Food',
            'Culture',
            'Beach',
            'Mountains',
            'Wildlife',
            'History',
            'Spiritual',
        ];

        foreach ($interests as $name) {
            Interest::firstOrCreate([
                'slug' => Str::slug($name),
            ], [
                'name' => $name,
            ]);
        }
    }
}
