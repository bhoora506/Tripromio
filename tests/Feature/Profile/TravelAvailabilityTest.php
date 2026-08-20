<?php

namespace Tests\Feature\Profile;

use App\Models\TravelAvailability;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3A — Travel Availability Tests
 */
class TravelAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    // ── Authentication ────────────────────────────────────────────────────────

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/profile/availability')->assertStatus(401);
        $this->postJson('/api/profile/availability', [])->assertStatus(401);
    }

    // ── List ──────────────────────────────────────────────────────────────────

    public function test_authenticated_user_can_list_own_availability(): void
    {
        $user = User::factory()->create();
        TravelAvailability::factory()->count(3)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/profile/availability');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'availabilities' => [
                        '*' => ['id', 'start_date', 'end_date'],
                    ],
                ],
            ]);

        $this->assertCount(3, $response->json('data.availabilities'));
    }

    public function test_user_only_sees_own_availability(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        TravelAvailability::factory()->count(2)->create(['user_id' => $userA->id]);
        TravelAvailability::factory()->count(3)->create(['user_id' => $userB->id]);

        $response = $this->actingAs($userA, 'sanctum')
            ->getJson('/api/profile/availability');

        $this->assertCount(2, $response->json('data.availabilities'));
    }

    public function test_empty_list_returned_when_no_availability(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/profile/availability');

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data.availabilities'));
    }

    // ── Create ────────────────────────────────────────────────────────────────

    public function test_authenticated_user_can_create_availability(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/profile/availability', [
                'start_date' => '2027-09-20',
                'end_date'   => '2027-09-30',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.availability.start_date', '2027-09-20')
            ->assertJsonPath('data.availability.end_date', '2027-09-30');

        $this->assertDatabaseHas('travel_availabilities', [
            'user_id' => $user->id,
        ]);
    }

    public function test_user_cannot_supply_user_id_in_request(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        // Even if user_id is in the payload, it must be ignored
        $response = $this->actingAs($userA, 'sanctum')
            ->postJson('/api/profile/availability', [
                'user_id'    => $userB->id,
                'start_date' => '2027-09-20',
                'end_date'   => '2027-09-30',
            ]);

        $response->assertStatus(201);

        $created = TravelAvailability::where('user_id', $userA->id)->first();
        $this->assertNotNull($created);
        $this->assertSame($userA->id, $created->user_id);
    }

    public function test_user_can_have_multiple_availability_windows(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/profile/availability', [
                'start_date' => '2027-09-20',
                'end_date'   => '2027-09-30',
            ])
            ->assertStatus(201);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/profile/availability', [
                'start_date' => '2027-10-10',
                'end_date'   => '2027-10-15',
            ])
            ->assertStatus(201);

        $this->assertSame(2, TravelAvailability::where('user_id', $user->id)->count());
    }

    // ── Validation ────────────────────────────────────────────────────────────

    public function test_invalid_date_range_returns_422(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/profile/availability', [
                'start_date' => '2027-09-20',
                'end_date'   => '2027-09-15', // before start
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['end_date']);
    }

    public function test_start_date_is_required(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/profile/availability', ['end_date' => '2027-09-30'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['start_date']);
    }

    public function test_end_date_is_required(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/profile/availability', ['start_date' => '2027-09-20'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['end_date']);
    }

    public function test_same_day_availability_is_valid(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/profile/availability', [
                'start_date' => '2027-09-20',
                'end_date'   => '2027-09-20',
            ])
            ->assertStatus(201);
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function test_owner_can_update_own_availability(): void
    {
        $user         = User::factory()->create();
        $availability = TravelAvailability::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/profile/availability/{$availability->id}", [
                'start_date' => '2027-11-01',
                'end_date'   => '2027-11-10',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.availability.start_date', '2027-11-01')
            ->assertJsonPath('data.availability.end_date', '2027-11-10');
    }

    public function test_user_cannot_update_another_users_availability(): void
    {
        $userA        = User::factory()->create();
        $userB        = User::factory()->create();
        $availability = TravelAvailability::factory()->create(['user_id' => $userA->id]);

        $this->actingAs($userB, 'sanctum')
            ->putJson("/api/profile/availability/{$availability->id}", [
                'start_date' => '2027-11-01',
                'end_date'   => '2027-11-10',
            ])
            ->assertStatus(403);
    }

    // ── Delete ────────────────────────────────────────────────────────────────

    public function test_owner_can_delete_own_availability(): void
    {
        $user         = User::factory()->create();
        $availability = TravelAvailability::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/profile/availability/{$availability->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('travel_availabilities', ['id' => $availability->id]);
    }

    public function test_user_cannot_delete_another_users_availability(): void
    {
        $userA        = User::factory()->create();
        $userB        = User::factory()->create();
        $availability = TravelAvailability::factory()->create(['user_id' => $userA->id]);

        $this->actingAs($userB, 'sanctum')
            ->deleteJson("/api/profile/availability/{$availability->id}")
            ->assertStatus(403);

        // Record still exists
        $this->assertDatabaseHas('travel_availabilities', ['id' => $availability->id]);
    }
}
