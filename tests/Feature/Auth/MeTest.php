<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_get_their_profile(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/auth/me');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Authenticated user',
            ])
            ->assertJsonStructure([
                'data' => ['user' => ['id', 'name', 'email']],
            ]);
    }

    public function test_correct_user_is_returned(): void
    {
        $user = User::factory()->create(['email' => 'arjun@example.com']);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/auth/me');

        $response->assertJsonPath('data.user.email', 'arjun@example.com');
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->getJson('/api/auth/me');

        $response->assertStatus(401)
            ->assertJson(['success' => false]);
    }

    public function test_password_is_not_in_me_response(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/auth/me');

        $response->assertJsonMissing(['password']);
    }
}
