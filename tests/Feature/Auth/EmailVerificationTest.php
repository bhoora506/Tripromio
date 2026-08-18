<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_verification_email_sent_on_registration(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/register', [
            'name'                  => 'Arjun Kumar',
            'email'                 => 'arjun@example.com',
            'password'              => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ]);

        $user = User::where('email', 'arjun@example.com')->first();
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_user_can_verify_email_with_valid_signed_url(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        // Strip the /api prefix that the URL helper adds for named routes under the api group.
        $path = parse_url($url, PHP_URL_PATH);
        $query = parse_url($url, PHP_URL_QUERY);
        $relativeUrl = $path . ($query ? '?' . $query : '');

        $response = $this->actingAs($user, 'sanctum')
            ->getJson($relativeUrl);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Email address verified successfully',
            ]);

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/email/verify/{$user->id}/invalid-hash");

        $response->assertStatus(403);
    }

    public function test_already_verified_user_gets_graceful_response(): void
    {
        // User factory creates verified users by default.
        $user = User::factory()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $path = parse_url($url, PHP_URL_PATH);
        $query = parse_url($url, PHP_URL_QUERY);
        $relativeUrl = $path . ($query ? '?' . $query : '');

        $response = $this->actingAs($user, 'sanctum')
            ->getJson($relativeUrl);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Email address is already verified',
            ]);
    }

    public function test_resend_sends_notification_to_unverified_user(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/email/verification-notification')
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Verification email has been resent',
            ]);

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_resend_returns_already_verified_for_verified_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/email/verification-notification');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Email address is already verified',
            ]);
    }

    public function test_unauthenticated_user_cannot_access_verify_endpoint(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->getJson("/api/email/verify/{$user->id}/somehash");

        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_resend_verification(): void
    {
        $response = $this->postJson('/api/email/verification-notification');

        $response->assertStatus(401);
    }
}
