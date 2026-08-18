<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_returns_generic_success_for_existing_email(): void
    {
        User::factory()->create(['email' => 'arjun@example.com']);

        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'arjun@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonFragment([
                'message' => 'If an account exists for this email, password reset instructions have been sent.',
            ]);
    }

    public function test_forgot_password_returns_generic_success_for_nonexistent_email(): void
    {
        // Must return same generic response to prevent user enumeration.
        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'nobody@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_email_is_required_for_forgot_password(): void
    {
        $response = $this->postJson('/api/auth/forgot-password', []);

        $response->assertStatus(422);
    }

    public function test_invalid_email_format_rejected_for_forgot_password(): void
    {
        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'not-an-email',
        ]);

        $response->assertStatus(422);
    }
}
