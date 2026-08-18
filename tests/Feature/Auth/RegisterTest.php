<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_with_valid_data(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'Arjun Kumar',
            'email'                 => 'arjun@example.com',
            'password'              => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => ['id', 'name', 'email'],
                    'token',
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Registration successful',
            ]);
    }

    public function test_user_is_persisted_in_database_after_registration(): void
    {
        $this->postJson('/api/auth/register', [
            'name'                  => 'Arjun Kumar',
            'email'                 => 'arjun@example.com',
            'password'              => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ]);

        $this->assertDatabaseHas('users', ['email' => 'arjun@example.com']);
    }

    public function test_password_is_hashed_in_database(): void
    {
        $this->postJson('/api/auth/register', [
            'name'                  => 'Arjun Kumar',
            'email'                 => 'arjun@example.com',
            'password'              => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ]);

        $user = User::where('email', 'arjun@example.com')->first();
        $this->assertNotEquals('Secret123!', $user->password);
        $this->assertTrue(password_verify('Secret123!', $user->password));
    }

    public function test_password_is_not_returned_in_response(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'Arjun Kumar',
            'email'                 => 'arjun@example.com',
            'password'              => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ]);

        $response->assertJsonMissing(['password']);
    }

    public function test_token_is_returned_after_registration(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'Arjun Kumar',
            'email'                 => 'arjun@example.com',
            'password'              => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ]);

        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_duplicate_email_is_rejected(): void
    {
        User::factory()->create(['email' => 'arjun@example.com']);

        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'Another User',
            'email'                 => 'arjun@example.com',
            'password'              => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    public function test_invalid_email_format_is_rejected(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'Arjun Kumar',
            'email'                 => 'not-an-email',
            'password'              => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ]);

        $response->assertStatus(422);
    }

    public function test_password_confirmation_mismatch_is_rejected(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'Arjun Kumar',
            'email'                 => 'arjun@example.com',
            'password'              => 'Secret123!',
            'password_confirmation' => 'DifferentPass!',
        ]);

        $response->assertStatus(422);
    }

    public function test_weak_password_is_rejected(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'Arjun Kumar',
            'email'                 => 'arjun@example.com',
            'password'              => 'weak',
            'password_confirmation' => 'weak',
        ]);

        $response->assertStatus(422);
    }

    public function test_name_is_required(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'email'                 => 'arjun@example.com',
            'password'              => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('errors.name', fn ($v) => ! empty($v));
    }
}
