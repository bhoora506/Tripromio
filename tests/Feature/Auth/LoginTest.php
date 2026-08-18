<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_valid_credentials(): void
    {
        User::factory()->create([
            'email'    => 'arjun@example.com',
            'password' => bcrypt('Secret123!'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'arjun@example.com',
            'password' => 'Secret123!',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['user', 'token'],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Login successful',
            ]);
    }

    public function test_token_is_returned_on_successful_login(): void
    {
        User::factory()->create([
            'email'    => 'arjun@example.com',
            'password' => bcrypt('Secret123!'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'arjun@example.com',
            'password' => 'Secret123!',
        ]);

        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_password_is_not_returned_on_login(): void
    {
        User::factory()->create([
            'email'    => 'arjun@example.com',
            'password' => bcrypt('Secret123!'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'arjun@example.com',
            'password' => 'Secret123!',
        ]);

        $response->assertJsonMissing(['password']);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email'    => 'arjun@example.com',
            'password' => bcrypt('Secret123!'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'arjun@example.com',
            'password' => 'WrongPassword!',
        ]);

        $response->assertStatus(401)
            ->assertJson(['success' => false]);
    }

    public function test_login_fails_with_unknown_email(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email'    => 'nobody@example.com',
            'password' => 'Secret123!',
        ]);

        $response->assertStatus(401)
            ->assertJson(['success' => false]);
    }

    public function test_email_is_required_for_login(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'password' => 'Secret123!',
        ]);

        $response->assertStatus(422);
    }

    public function test_password_is_required_for_login(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'arjun@example.com',
        ]);

        $response->assertStatus(422);
    }
}
