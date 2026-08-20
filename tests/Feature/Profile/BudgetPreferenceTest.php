<?php

namespace Tests\Feature\Profile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3A — Budget Preferences Tests
 */
class BudgetPreferenceTest extends TestCase
{
    use RefreshDatabase;

    // ── Valid values ──────────────────────────────────────────────────────────

    public function test_user_can_set_budget_preferences(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/profile', [
                'preferred_budget_min' => 5000,
                'preferred_budget_max' => 8000,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.user.profile.preferred_budget_min', '5000.00')
            ->assertJsonPath('data.user.profile.preferred_budget_max', '8000.00');
    }

    public function test_only_budget_min_is_valid(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/profile', ['preferred_budget_min' => 5000])
            ->assertStatus(200)
            ->assertJsonPath('data.user.profile.preferred_budget_min', '5000.00');
    }

    public function test_only_budget_max_is_valid(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/profile', ['preferred_budget_max' => 10000])
            ->assertStatus(200)
            ->assertJsonPath('data.user.profile.preferred_budget_max', '10000.00');
    }

    public function test_both_budget_fields_null_is_valid(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/profile', [
                'preferred_budget_min' => null,
                'preferred_budget_max' => null,
            ])
            ->assertStatus(200);
    }

    // ── Validation ────────────────────────────────────────────────────────────

    public function test_reversed_budget_range_returns_422(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/profile', [
                'preferred_budget_min' => 8000,
                'preferred_budget_max' => 5000, // less than min
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['preferred_budget_max']);
    }

    public function test_negative_budget_min_returns_422(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/profile', ['preferred_budget_min' => -100])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['preferred_budget_min']);
    }

    public function test_negative_budget_max_returns_422(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/profile', ['preferred_budget_max' => -1])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['preferred_budget_max']);
    }

    // ── Profile resource ──────────────────────────────────────────────────────

    public function test_profile_resource_exposes_budget_fields(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/profile', [
                'preferred_budget_min' => 3000,
                'preferred_budget_max' => 7000,
            ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/profile')
            ->assertStatus(200)
            ->assertJsonPath('data.user.profile.preferred_budget_min', '3000.00')
            ->assertJsonPath('data.user.profile.preferred_budget_max', '7000.00');
    }

    public function test_profile_resource_shows_null_when_budget_not_set(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/profile')
            ->assertStatus(200)
            ->assertJsonPath('data.user.profile.preferred_budget_min', null)
            ->assertJsonPath('data.user.profile.preferred_budget_max', null);
    }

    // ── Not in profile completion ──────────────────────────────────────────────

    public function test_budget_preferences_do_not_affect_profile_completion(): void
    {
        $user = User::factory()->create();

        // Profile without budget preferences
        $this->actingAs($user, 'sanctum')->getJson('/api/profile');
        $baseCompletion = $this->actingAs($user, 'sanctum')
            ->getJson('/api/profile')
            ->json('data.user.profile_completion');

        // Add budget preferences
        $this->actingAs($user, 'sanctum')->putJson('/api/profile', [
            'preferred_budget_min' => 5000,
            'preferred_budget_max' => 10000,
        ]);

        $newCompletion = $this->actingAs($user, 'sanctum')
            ->getJson('/api/profile')
            ->json('data.user.profile_completion');

        // Profile completion should not change when budget is added
        $this->assertSame($baseCompletion, $newCompletion);
    }
}
