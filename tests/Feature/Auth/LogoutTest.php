<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_logout(): void
    {
        $user  = User::factory()->create();
        $token = $user->createToken('auth_token');

        $response = $this->withToken($token->plainTextToken)
            ->postJson('/api/auth/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Logout successful',
            ]);
    }

    public function test_unauthenticated_logout_returns_401(): void
    {
        $response = $this->postJson('/api/auth/logout');

        $response->assertStatus(401);
    }

    public function test_token_is_revoked_after_logout(): void
    {
        $user  = User::factory()->create();
        $token = $user->createToken('auth_token');

        // Log out using the token that was just created.
        $this->withToken($token->plainTextToken)
            ->postJson('/api/auth/logout')
            ->assertStatus(200);

        // Flush the in-memory auth guard cache so the next request
        // re-resolves the token from the database (where it is now deleted).
        Auth::forgetGuards();

        // The same token must no longer grant access.
        $this->withToken($token->plainTextToken)
            ->getJson('/api/auth/me')
            ->assertStatus(401);
    }

    public function test_other_tokens_are_not_revoked_on_logout(): void
    {
        $user        = User::factory()->create();
        $activeToken = $user->createToken('device_2');
        $logoutToken = $user->createToken('device_1');

        // Logout only revokes the token used to make the request.
        $this->withToken($logoutToken->plainTextToken)
            ->postJson('/api/auth/logout');

        Auth::forgetGuards();

        // The other token should still work.
        $this->withToken($activeToken->plainTextToken)
            ->getJson('/api/auth/me')
            ->assertStatus(200);
    }
}


