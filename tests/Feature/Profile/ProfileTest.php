<?php

namespace Tests\Feature\Profile;

use App\Enums\TravelStyle;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_profile(): void
    {
        $response = $this->getJson('/api/profile');
        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_get_profile(): void
    {
        $user = User::factory()->create();
        $user->profile()->create([
            'bio' => 'Test bio',
            'city' => 'Paris',
            'country' => 'France',
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/profile');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'profile' => [
                            'bio' => 'Test bio',
                            'city' => 'Paris',
                            'country' => 'France',
                        ]
                    ]
                ]
            ]);
    }

    public function test_user_can_create_or_update_profile(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->putJson('/api/profile', [
            'bio' => 'Updated bio',
            'city' => 'London',
            'country' => 'UK',
            'languages' => ['English', 'French'],
            'travel_style' => TravelStyle::Adventure->value,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.user.profile.bio', 'Updated bio')
            ->assertJsonPath('data.user.profile.languages.0', 'English')
            ->assertJsonPath('data.user.profile.travel_style', 'adventure');

        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $user->id,
            'city' => 'London',
        ]);
    }

    public function test_invalid_travel_style_is_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->putJson('/api/profile', [
            'travel_style' => 'not_a_valid_style',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['travel_style']);
    }

    public function test_languages_must_be_an_array(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->putJson('/api/profile', [
            'languages' => 'English, French', // Invalid, should be array
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['languages']);
    }

    public function test_excessive_languages_array_is_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->putJson('/api/profile', [
            'languages' => array_fill(0, 15, 'Language'), // Max is 10
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['languages']);
    }
}
