<?php

namespace Tests\Feature\Profile;

use App\Models\Interest;
use App\Models\User;
use App\Services\ProfileCompletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_profile_is_zero_percent(): void
    {
        $user = User::factory()->create();
        $service = new ProfileCompletionService();

        $this->assertEquals(0, $service->calculate($user));
    }

    public function test_full_profile_is_one_hundred_percent(): void
    {
        $user = User::factory()->create();
        $user->profile()->create([
            'profile_photo_path' => 'path/to/photo.jpg',
            'bio' => 'Test bio',
            'city' => 'City',
            'country' => 'Country',
            'languages' => ['English'],
            'travel_style' => 'adventure',
        ]);

        $interest = Interest::create(['name' => 'Trekking', 'slug' => 'trekking']);
        $user->interests()->attach($interest);

        $service = new ProfileCompletionService();
        $this->assertEquals(100, $service->calculate($user->fresh(['profile', 'interests'])));
    }

    public function test_partial_profile_calculates_correctly(): void
    {
        $user = User::factory()->create();
        // Just bio (15%) and city (15%) = 30%
        $user->profile()->create([
            'bio' => 'Test bio',
            'city' => 'City',
        ]);

        $service = new ProfileCompletionService();
        $this->assertEquals(30, $service->calculate($user->fresh(['profile', 'interests'])));
    }

    public function test_completion_is_returned_in_profile_api(): void
    {
        $user = User::factory()->create();
        $user->profile()->create(['bio' => 'Test bio']); // 15%

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/profile');

        $response->assertStatus(200)
            ->assertJsonPath('data.user.profile_completion', 15);
    }
}
