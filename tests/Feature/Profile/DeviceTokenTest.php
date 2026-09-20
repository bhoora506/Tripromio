<?php

namespace Tests\Feature\Profile;

use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * H1-B Feature Tests — Device Token Registration API.
 *
 * Covers all 14 scenarios required in Part 15 plus edge cases found
 * during implementation.
 *
 * Endpoints under test:
 *   POST   /api/profile/device-token
 *   DELETE /api/profile/device-token
 *
 * Convention mirrors the project's existing test style:
 *   - Uses RefreshDatabase
 *   - actingAs($user, 'sanctum') for authenticated requests
 *   - assertDatabaseHas / assertDatabaseMissing for DB assertions
 *   - assertJsonPath for response structure
 */
class DeviceTokenTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function registerUrl(): string
    {
        return '/api/profile/device-token';
    }

    private function unregisterUrl(): string
    {
        return '/api/profile/device-token';
    }

    private function validRegisterPayload(string $token = 'test-fcm-token-android-001'): array
    {
        return [
            'fcm_token' => $token,
            'platform'  => 'android',
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 1. Unauthenticated user cannot register token
    // ══════════════════════════════════════════════════════════════════════════

    public function test_unauthenticated_user_cannot_register_device_token(): void
    {
        $response = $this->postJson($this->registerUrl(), $this->validRegisterPayload());

        $response->assertStatus(401);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 2. Authenticated user can register Android token
    // ══════════════════════════════════════════════════════════════════════════

    public function test_authenticated_user_can_register_android_token(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson($this->registerUrl(), $this->validRegisterPayload());

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => ['registered' => true],
            ]);

        $this->assertDatabaseHas('user_devices', [
            'user_id'   => $user->id,
            'fcm_token' => 'test-fcm-token-android-001',
            'platform'  => 'android',
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 3. Same token registration is idempotent
    // ══════════════════════════════════════════════════════════════════════════

    public function test_same_token_registration_is_idempotent(): void
    {
        $user = User::factory()->create();

        // First registration
        $this->actingAs($user, 'sanctum')
            ->postJson($this->registerUrl(), $this->validRegisterPayload())
            ->assertStatus(200);

        // Second identical registration — still 200
        $response = $this->actingAs($user, 'sanctum')
            ->postJson($this->registerUrl(), $this->validRegisterPayload());

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 4. Repeated registration does not create duplicate token rows
    // ══════════════════════════════════════════════════════════════════════════

    public function test_repeated_registration_does_not_create_duplicate_rows(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson($this->registerUrl(), $this->validRegisterPayload());

        $this->actingAs($user, 'sanctum')
            ->postJson($this->registerUrl(), $this->validRegisterPayload());

        $this->actingAs($user, 'sanctum')
            ->postJson($this->registerUrl(), $this->validRegisterPayload());

        $count = UserDevice::where('fcm_token', 'test-fcm-token-android-001')->count();
        $this->assertSame(1, $count, 'Only one row should exist for the same token.');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 5. Token refresh / update updates last_used_at
    // ══════════════════════════════════════════════════════════════════════════

    public function test_repeated_registration_refreshes_last_used_at(): void
    {
        $user = User::factory()->create();
        $token = 'test-fcm-token-refresh-001';

        // Create an initial record with an old last_used_at (1 hour ago).
        $oldTime = now()->subHour();
        UserDevice::create([
            'user_id'      => $user->id,
            'fcm_token'    => $token,
            'platform'     => 'android',
            'last_used_at' => $oldTime,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson($this->registerUrl(), [
                'fcm_token' => $token,
                'platform'  => 'android',
            ])
            ->assertStatus(200);

        $device = UserDevice::where('fcm_token', $token)->first();
        $this->assertNotNull($device);
        $this->assertNotNull($device->last_used_at);

        // last_used_at must be more recent than the original old timestamp.
        // We compare at the minute level to avoid sub-second flakiness.
        $this->assertTrue(
            $device->last_used_at->gt($oldTime),
            'last_used_at should have been updated to a more recent time than the original.'
        );
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 6. Existing token can be reassigned to authenticated user
    // ══════════════════════════════════════════════════════════════════════════

    public function test_existing_token_is_reassigned_to_new_authenticated_user(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $token = 'shared-device-token-001';

        // Token initially belongs to user A.
        UserDevice::create([
            'user_id'      => $userA->id,
            'fcm_token'    => $token,
            'platform'     => 'android',
            'last_used_at' => now(),
        ]);

        // User B logs in on the same device and registers the same token.
        $this->actingAs($userB, 'sanctum')
            ->postJson($this->registerUrl(), [
                'fcm_token' => $token,
                'platform'  => 'android',
            ])
            ->assertStatus(200);

        // Token should now belong to user B.
        $device = UserDevice::where('fcm_token', $token)->first();
        $this->assertSame($userB->id, $device->user_id);

        // Exactly one row should exist (no duplicate).
        $this->assertSame(1, UserDevice::where('fcm_token', $token)->count());
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 7. Invalid/missing fcm_token returns validation error
    // ══════════════════════════════════════════════════════════════════════════

    public function test_missing_fcm_token_returns_validation_error(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson($this->registerUrl(), ['platform' => 'android']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fcm_token']);
    }

    public function test_empty_fcm_token_returns_validation_error(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson($this->registerUrl(), ['fcm_token' => '', 'platform' => 'android']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fcm_token']);
    }

    public function test_token_exceeding_max_length_returns_validation_error(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson($this->registerUrl(), [
                'fcm_token' => str_repeat('a', 256), // max is 255
                'platform'  => 'android',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fcm_token']);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 8. Invalid platform is rejected
    // ══════════════════════════════════════════════════════════════════════════

    public function test_invalid_platform_is_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson($this->registerUrl(), [
                'fcm_token' => 'valid-token-001',
                'platform'  => 'windows',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['platform']);
    }

    public function test_missing_platform_returns_validation_error(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson($this->registerUrl(), ['fcm_token' => 'valid-token-001']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['platform']);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 9. iOS platform accepted by validation
    // ══════════════════════════════════════════════════════════════════════════

    public function test_ios_platform_is_accepted_by_validation(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson($this->registerUrl(), [
                'fcm_token' => 'ios-apns-token-001',
                'platform'  => 'ios',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('user_devices', [
            'user_id'  => $user->id,
            'platform' => 'ios',
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 10. User cannot remove another user's token
    // ══════════════════════════════════════════════════════════════════════════

    public function test_user_cannot_remove_another_users_device_token(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $token = 'userA-device-token-001';

        UserDevice::create([
            'user_id'      => $userA->id,
            'fcm_token'    => $token,
            'platform'     => 'android',
            'last_used_at' => now(),
        ]);

        // User B tries to delete user A's token.
        $response = $this->actingAs($userB, 'sanctum')
            ->deleteJson($this->unregisterUrl(), ['fcm_token' => $token]);

        // 200 (idempotent) but the row must still exist for user A.
        $response->assertStatus(200);

        $this->assertDatabaseHas('user_devices', [
            'user_id'   => $userA->id,
            'fcm_token' => $token,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 11. Authenticated user can remove their own token
    // ══════════════════════════════════════════════════════════════════════════

    public function test_authenticated_user_can_remove_their_own_token(): void
    {
        $user  = User::factory()->create();
        $token = 'my-device-token-001';

        UserDevice::create([
            'user_id'      => $user->id,
            'fcm_token'    => $token,
            'platform'     => 'android',
            'last_used_at' => now(),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson($this->unregisterUrl(), ['fcm_token' => $token]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => ['unregistered' => true],
            ]);

        $this->assertDatabaseMissing('user_devices', [
            'fcm_token' => $token,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 12. Removing a non-existing token behaves safely / idempotently
    // ══════════════════════════════════════════════════════════════════════════

    public function test_removing_nonexistent_token_is_safe_and_idempotent(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson($this->unregisterUrl(), [
                'fcm_token' => 'this-token-does-not-exist',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 13. Token is not exposed in API resources
    // ══════════════════════════════════════════════════════════════════════════

    public function test_fcm_token_is_not_exposed_in_profile_response(): void
    {
        $user  = User::factory()->create();
        $token = 'secret-device-token-001';

        // Register the token so it exists in the DB.
        $this->actingAs($user, 'sanctum')
            ->postJson($this->registerUrl(), [
                'fcm_token' => $token,
                'platform'  => 'android',
            ]);

        // Fetch the user's profile — token must not appear in the response.
        $profileResponse = $this->actingAs($user, 'sanctum')
            ->getJson('/api/profile');

        $profileResponse->assertStatus(200);

        $content = $profileResponse->getContent();
        $this->assertStringNotContainsString(
            $token,
            $content,
            'fcm_token must not appear in the profile API response.'
        );
    }

    public function test_fcm_token_is_not_exposed_in_registration_response(): void
    {
        $user  = User::factory()->create();
        $token = 'secret-device-token-register-001';

        $response = $this->actingAs($user, 'sanctum')
            ->postJson($this->registerUrl(), [
                'fcm_token' => $token,
                'platform'  => 'android',
            ]);

        $response->assertStatus(200);

        // The registration response data must only contain { registered: true }
        $response->assertJsonPath('data.registered', true);
        $content = $response->getContent();
        $this->assertStringNotContainsString(
            $token,
            $content,
            'fcm_token must not appear in the device-token registration response.'
        );
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 14. Existing authentication behavior remains intact
    // ══════════════════════════════════════════════════════════════════════════

    public function test_device_token_registration_requires_authentication(): void
    {
        // No actingAs — should be 401.
        $response = $this->postJson($this->registerUrl(), $this->validRegisterPayload());
        $response->assertStatus(401);
    }

    public function test_device_token_removal_requires_authentication(): void
    {
        // No actingAs — should be 401.
        $response = $this->deleteJson($this->unregisterUrl(), ['fcm_token' => 'some-token']);
        $response->assertStatus(401);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Additional edge cases
    // ══════════════════════════════════════════════════════════════════════════

    public function test_response_envelope_matches_project_convention(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson($this->registerUrl(), $this->validRegisterPayload());

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['registered'],
            ])
            ->assertJsonPath('success', true);
    }

    public function test_multiple_devices_can_be_registered_for_same_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson($this->registerUrl(), [
                'fcm_token' => 'device-token-phone-001',
                'platform'  => 'android',
            ])
            ->assertStatus(200);

        $this->actingAs($user, 'sanctum')
            ->postJson($this->registerUrl(), [
                'fcm_token' => 'device-token-tablet-001',
                'platform'  => 'android',
            ])
            ->assertStatus(200);

        $this->assertSame(2, UserDevice::where('user_id', $user->id)->count());
    }

    public function test_user_id_cannot_be_spoofed_via_request_body(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        // Authenticated as user A, but try to pass user B's id.
        // The backend must ignore any user_id in the body and use Sanctum session.
        $this->actingAs($userA, 'sanctum')
            ->postJson($this->registerUrl(), [
                'fcm_token' => 'spoofed-token-001',
                'platform'  => 'android',
                'user_id'   => $userB->id,  // should be ignored
            ])
            ->assertStatus(200);

        // Row must be created for user A (the authenticated user), not user B.
        $this->assertDatabaseHas('user_devices', [
            'fcm_token' => 'spoofed-token-001',
            'user_id'   => $userA->id,
        ]);

        $this->assertDatabaseMissing('user_devices', [
            'fcm_token' => 'spoofed-token-001',
            'user_id'   => $userB->id,
        ]);
    }
}
