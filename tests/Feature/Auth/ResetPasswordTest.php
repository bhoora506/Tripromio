<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class ResetPasswordTest extends TestCase
{
    use RefreshDatabase;

    private function getResetToken(User $user): string
    {
        return Password::createToken($user);
    }

    public function test_user_can_reset_password_with_valid_token(): void
    {
        $user  = User::factory()->create(['email' => 'arjun@example.com']);
        $token = $this->getResetToken($user);

        $response = $this->postJson('/api/auth/reset-password', [
            'token'                 => $token,
            'email'                 => 'arjun@example.com',
            'password'              => 'NewSecret123!',
            'password_confirmation' => 'NewSecret123!',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Password has been reset successfully',
            ]);
    }

    public function test_new_password_is_saved_in_database(): void
    {
        $user  = User::factory()->create(['email' => 'arjun@example.com']);
        $token = $this->getResetToken($user);

        $this->postJson('/api/auth/reset-password', [
            'token'                 => $token,
            'email'                 => 'arjun@example.com',
            'password'              => 'NewSecret123!',
            'password_confirmation' => 'NewSecret123!',
        ]);

        $user->refresh();
        $this->assertTrue(Hash::check('NewSecret123!', $user->password));
    }

    public function test_old_password_no_longer_works_after_reset(): void
    {
        $user  = User::factory()->create([
            'email'    => 'arjun@example.com',
            'password' => bcrypt('OldSecret123!'),
        ]);
        $token = $this->getResetToken($user);

        $this->postJson('/api/auth/reset-password', [
            'token'                 => $token,
            'email'                 => 'arjun@example.com',
            'password'              => 'NewSecret123!',
            'password_confirmation' => 'NewSecret123!',
        ]);

        $this->postJson('/api/auth/login', [
            'email'    => 'arjun@example.com',
            'password' => 'OldSecret123!',
        ])->assertStatus(401);
    }

    public function test_invalid_token_is_rejected(): void
    {
        User::factory()->create(['email' => 'arjun@example.com']);

        $response = $this->postJson('/api/auth/reset-password', [
            'token'                 => 'invalid-token',
            'email'                 => 'arjun@example.com',
            'password'              => 'NewSecret123!',
            'password_confirmation' => 'NewSecret123!',
        ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    public function test_password_confirmation_required_for_reset(): void
    {
        $user  = User::factory()->create(['email' => 'arjun@example.com']);
        $token = $this->getResetToken($user);

        $response = $this->postJson('/api/auth/reset-password', [
            'token'                 => $token,
            'email'                 => 'arjun@example.com',
            'password'              => 'NewSecret123!',
            'password_confirmation' => 'Mismatch123!',
        ]);

        $response->assertStatus(422);
    }
}
