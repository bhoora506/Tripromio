<?php

namespace Tests\Feature\Trip;

use App\Models\Interest;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3A — Trip Interests Tests
 */
class TripInterestTest extends TestCase
{
    use RefreshDatabase;

    // ── Create with interests ─────────────────────────────────────────────────

    public function test_trip_can_be_created_with_interests(): void
    {
        $user      = User::factory()->create();
        $interests = Interest::factory()->count(3)->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/trips', array_merge($this->validTripPayload(), [
                'interest_ids' => $interests->pluck('id')->all(),
            ]));

        $response->assertStatus(201);

        $trip = Trip::where('user_id', $user->id)->first();
        $this->assertCount(3, $trip->interests);
    }

    public function test_trip_resource_includes_interests(): void
    {
        $user     = User::factory()->create();
        $interest = Interest::factory()->create(['name' => 'Trekking', 'slug' => 'trekking']);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/trips', array_merge($this->validTripPayload(), [
                'interest_ids' => [$interest->id],
            ]));

        $response->assertStatus(201)
            ->assertJsonPath('data.trip.interests.0.id', $interest->id)
            ->assertJsonPath('data.trip.interests.0.name', 'Trekking')
            ->assertJsonPath('data.trip.interests.0.slug', 'trekking');
    }

    public function test_trip_can_be_created_without_interests(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/trips', $this->validTripPayload());

        $response->assertStatus(201);
        $response->assertJsonPath('data.trip.interests', []);
    }

    // ── Update trip interests ─────────────────────────────────────────────────

    public function test_owner_can_update_trip_interests(): void
    {
        $user      = User::factory()->create();
        $trip      = Trip::factory()->create(['user_id' => $user->id]);
        $interests = Interest::factory()->count(2)->create();

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/trips/{$trip->id}", [
                'interest_ids' => $interests->pluck('id')->all(),
            ])
            ->assertStatus(200)
            ->assertJsonCount(2, 'data.trip.interests');
    }

    public function test_updating_interests_syncs_and_removes_old_ones(): void
    {
        $user     = User::factory()->create();
        $trip     = Trip::factory()->create(['user_id' => $user->id]);
        $oldInterest = Interest::factory()->create();
        $newInterest = Interest::factory()->create();

        $trip->interests()->sync([$oldInterest->id]);
        $this->assertCount(1, $trip->fresh()->interests);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/trips/{$trip->id}", [
                'interest_ids' => [$newInterest->id],
            ])
            ->assertStatus(200);

        $fresh = $trip->fresh();
        $this->assertCount(1, $fresh->interests);
        $this->assertSame($newInterest->id, $fresh->interests->first()->id);
    }

    public function test_updating_interests_with_empty_array_clears_interests(): void
    {
        $user     = User::factory()->create();
        $trip     = Trip::factory()->create(['user_id' => $user->id]);
        $interest = Interest::factory()->create();
        $trip->interests()->sync([$interest->id]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/trips/{$trip->id}", ['interest_ids' => []])
            ->assertStatus(200);

        $this->assertCount(0, $trip->fresh()->interests);
    }

    public function test_non_owner_cannot_update_trip_interests(): void
    {
        $owner    = User::factory()->create();
        $stranger = User::factory()->create();
        $trip     = Trip::factory()->create(['user_id' => $owner->id]);
        $interest = Interest::factory()->create();

        $this->actingAs($stranger, 'sanctum')
            ->putJson("/api/trips/{$trip->id}", ['interest_ids' => [$interest->id]])
            ->assertStatus(403);
    }

    // ── Validation ────────────────────────────────────────────────────────────

    public function test_invalid_interest_id_returns_422(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/trips', array_merge($this->validTripPayload(), [
                'interest_ids' => [99999], // non-existent
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['interest_ids.0']);
    }

    public function test_more_than_ten_interests_returns_422(): void
    {
        $user      = User::factory()->create();
        $interests = Interest::factory()->count(11)->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/trips', array_merge($this->validTripPayload(), [
                'interest_ids' => $interests->pluck('id')->all(),
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['interest_ids']);
    }

    public function test_duplicate_interest_ids_are_normalized(): void
    {
        $user     = User::factory()->create();
        $interest = Interest::factory()->create();

        // Same ID twice — should not cause unique constraint violation
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/trips', array_merge($this->validTripPayload(), [
                'interest_ids' => [$interest->id, $interest->id],
            ]));

        $response->assertStatus(201);

        $trip = Trip::where('user_id', $user->id)->first();
        $this->assertCount(1, $trip->interests); // deduplicated
    }

    // ── Trip show includes interests ──────────────────────────────────────────

    public function test_show_trip_includes_interests(): void
    {
        $user     = User::factory()->create();
        $interest = Interest::factory()->create();
        $trip     = Trip::factory()->create(['user_id' => $user->id]);
        $trip->interests()->sync([$interest->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/trips/{$trip->id}")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.trip.interests')
            ->assertJsonPath('data.trip.interests.0.id', $interest->id);
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    private function validTripPayload(): array
    {
        return [
            'title'       => 'Interest Test Trip',
            'destination' => 'Udaipur',
            'start_date'  => now()->addDays(10)->toDateString(),
            'end_date'    => now()->addDays(15)->toDateString(),
            'trip_type'   => 'adventure',
            'max_members' => 4,
        ];
    }
}
