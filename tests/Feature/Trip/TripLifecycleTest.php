<?php

namespace Tests\Feature\Trip;

use App\Enums\TripStatus;
use App\Enums\TripType;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TripLifecycleTest extends TestCase
{
    use RefreshDatabase;

    // ── Publish ───────────────────────────────────────────────────────────────

    public function test_owner_can_publish_a_draft_trip(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/trips/{$trip->id}/publish")
            ->assertStatus(200)
            ->assertJsonPath('data.trip.status', 'published');

        $this->assertSame('published', $trip->fresh()->status->value);
    }

    public function test_non_owner_cannot_publish_trip(): void
    {
        $owner    = User::factory()->create();
        $stranger = User::factory()->create();
        $trip     = Trip::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($stranger, 'sanctum')
            ->postJson("/api/trips/{$trip->id}/publish")
            ->assertStatus(403);
    }

    public function test_already_published_trip_cannot_be_published_again(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->published()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/trips/{$trip->id}/publish")
            ->assertStatus(409);
    }

    public function test_ongoing_trip_cannot_be_published(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->ongoing()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/trips/{$trip->id}/publish")
            ->assertStatus(409);
    }

    public function test_completed_trip_cannot_be_published(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->completed()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/trips/{$trip->id}/publish")
            ->assertStatus(409);
    }

    public function test_cancelled_trip_cannot_be_published(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->cancelled()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/trips/{$trip->id}/publish")
            ->assertStatus(409);
    }

    // ── Cancel ────────────────────────────────────────────────────────────────

    public function test_owner_can_cancel_a_draft_trip(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/trips/{$trip->id}/cancel")
            ->assertStatus(200)
            ->assertJsonPath('data.trip.status', 'cancelled');
    }

    public function test_owner_can_cancel_a_published_trip(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->published()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/trips/{$trip->id}/cancel")
            ->assertStatus(200)
            ->assertJsonPath('data.trip.status', 'cancelled');
    }

    public function test_owner_can_cancel_an_ongoing_trip(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->ongoing()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/trips/{$trip->id}/cancel")
            ->assertStatus(200)
            ->assertJsonPath('data.trip.status', 'cancelled');
    }

    public function test_non_owner_cannot_cancel_trip(): void
    {
        $owner    = User::factory()->create();
        $stranger = User::factory()->create();
        $trip     = Trip::factory()->published()->create(['user_id' => $owner->id]);

        $this->actingAs($stranger, 'sanctum')
            ->postJson("/api/trips/{$trip->id}/cancel")
            ->assertStatus(403);
    }

    public function test_completed_trip_cannot_be_cancelled(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->completed()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/trips/{$trip->id}/cancel")
            ->assertStatus(409);
    }

    public function test_already_cancelled_trip_cannot_be_cancelled_again(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->cancelled()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/trips/{$trip->id}/cancel")
            ->assertStatus(409);
    }

    public function test_cancelled_trip_is_not_deleted(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/trips/{$trip->id}/cancel")
            ->assertStatus(200);

        $this->assertDatabaseHas('trips', ['id' => $trip->id, 'status' => 'cancelled']);
    }

    // ── Full lifecycle smoke test ──────────────────────────────────────────────

    public function test_full_lifecycle_draft_to_completed(): void
    {
        $user = User::factory()->create();

        // Create
        $createResponse = $this->actingAs($user, 'sanctum')
            ->postJson('/api/trips', [
                'title'       => 'Lifecycle Trip',
                'destination' => 'Manali',
                'start_date'  => now()->addDays(10)->toDateString(),
                'end_date'    => now()->addDays(15)->toDateString(),
                'trip_type'   => TripType::Adventure->value,
                'max_members' => 3,
            ]);

        $createResponse->assertStatus(201);
        $tripId = $createResponse->json('data.trip.id');
        $this->assertEquals('draft', $createResponse->json('data.trip.status'));

        // Publish
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/trips/{$tripId}/publish")
            ->assertStatus(200)
            ->assertJsonPath('data.trip.status', 'published');

        // Manually advance to ongoing (simulating time passing — Phase 2B doesn't auto-transition)
        Trip::find($tripId)->update(['status' => TripStatus::Ongoing->value]);

        // Cancel from ongoing
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/trips/{$tripId}/cancel")
            ->assertStatus(200)
            ->assertJsonPath('data.trip.status', 'cancelled');

        // Trip still exists in DB
        $this->assertDatabaseHas('trips', ['id' => $tripId, 'status' => 'cancelled']);
    }
}
