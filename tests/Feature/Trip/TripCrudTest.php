<?php

namespace Tests\Feature\Trip;

use App\Enums\MemberRole;
use App\Enums\MemberStatus;
use App\Enums\TripStatus;
use App\Enums\TripType;
use App\Models\Trip;
use App\Models\TripMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TripCrudTest extends TestCase
{
    use RefreshDatabase;

    // ── Create ────────────────────────────────────────────────────────────────

    public function test_unauthenticated_user_cannot_create_trip(): void
    {
        $response = $this->postJson('/api/trips', $this->validPayload());
        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_create_trip(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/trips', $this->validPayload());

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.trip.title', 'Udaipur Weekend')
            ->assertJsonPath('data.trip.status', 'draft')
            ->assertJsonPath('data.trip.owner.id', $user->id);
    }

    public function test_trip_is_always_created_as_draft(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/trips', $this->validPayload());

        $response->assertJsonPath('data.trip.status', 'draft');
        $this->assertDatabaseHas('trips', ['user_id' => $user->id, 'status' => 'draft']);
    }

    public function test_owner_membership_is_created_atomically(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/trips', $this->validPayload())
            ->assertStatus(201);

        $trip = Trip::where('user_id', $user->id)->first();
        $this->assertNotNull($trip);

        $member = TripMember::where('trip_id', $trip->id)
            ->where('user_id', $user->id)
            ->first();

        $this->assertNotNull($member);
        $this->assertSame(MemberRole::Owner->value, $member->role->value);
        $this->assertSame(MemberStatus::Active->value, $member->status->value);
        $this->assertNull($member->joined_at); // owner creates, not joins
    }

    public function test_user_id_from_request_is_ignored(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $payload = array_merge($this->validPayload(), ['user_id' => $other->id]);

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/trips', $payload)
            ->assertStatus(201);

        $trip = Trip::first();
        $this->assertSame($owner->id, $trip->user_id);
        $this->assertNotEquals($other->id, $trip->user_id);
    }

    // ── Validation ────────────────────────────────────────────────────────────

    public function test_title_is_required(): void
    {
        $user = User::factory()->create();
        $payload = $this->validPayload();
        unset($payload['title']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/trips', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    public function test_invalid_trip_type_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/trips', array_merge($this->validPayload(), ['trip_type' => 'invalid_type']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['trip_type']);
    }

    public function test_end_date_before_start_date_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/trips', array_merge($this->validPayload(), [
                'start_date' => '2027-09-20',
                'end_date'   => '2027-09-15', // before start
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['end_date']);
    }

    public function test_budget_max_less_than_budget_min_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/trips', array_merge($this->validPayload(), [
                'budget_min' => 10000,
                'budget_max' => 5000,
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['budget_max']);
    }

    public function test_max_members_below_two_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/trips', array_merge($this->validPayload(), ['max_members' => 1]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['max_members']);
    }

    public function test_max_members_above_twenty_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/trips', array_merge($this->validPayload(), ['max_members' => 25]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['max_members']);
    }

    // ── Read ──────────────────────────────────────────────────────────────────

    public function test_owner_can_view_their_trip(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/trips/{$trip->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.trip.id', $trip->id);
    }

    public function test_active_member_can_view_trip(): void
    {
        $owner  = User::factory()->create();
        $member = User::factory()->create();
        $trip   = Trip::factory()->create(['user_id' => $owner->id]);

        TripMember::factory()->create(['trip_id' => $trip->id, 'user_id' => $member->id]);

        $this->actingAs($member, 'sanctum')
            ->getJson("/api/trips/{$trip->id}")
            ->assertStatus(200);
    }

    public function test_authenticated_user_can_view_published_trip(): void
    {
        $owner   = User::factory()->create();
        $viewer  = User::factory()->create();
        $trip    = Trip::factory()->published()->create(['user_id' => $owner->id]);

        $this->actingAs($viewer, 'sanctum')
            ->getJson("/api/trips/{$trip->id}")
            ->assertStatus(200);
    }

    public function test_unauthenticated_user_cannot_view_any_trip(): void
    {
        $trip = Trip::factory()->published()->create();
        $this->getJson("/api/trips/{$trip->id}")->assertStatus(401);
    }

    public function test_unauthorized_user_cannot_view_private_draft_trip(): void
    {
        $owner   = User::factory()->create();
        $stranger = User::factory()->create();
        $trip    = Trip::factory()->create(['user_id' => $owner->id, 'status' => 'draft']);

        $this->actingAs($stranger, 'sanctum')
            ->getJson("/api/trips/{$trip->id}")
            ->assertStatus(403);
    }

    public function test_missing_trip_returns_404(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/trips/99999')
            ->assertStatus(404);
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function test_owner_can_update_draft_trip(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/trips/{$trip->id}", ['title' => 'Updated Title'])
            ->assertStatus(200)
            ->assertJsonPath('data.trip.title', 'Updated Title');
    }

    public function test_non_owner_cannot_update_trip(): void
    {
        $owner    = User::factory()->create();
        $stranger = User::factory()->create();
        $trip     = Trip::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($stranger, 'sanctum')
            ->putJson("/api/trips/{$trip->id}", ['title' => 'Hacked Title'])
            ->assertStatus(403);
    }

    public function test_completed_trip_cannot_be_updated(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->completed()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/trips/{$trip->id}", ['title' => 'New Title'])
            ->assertStatus(409);
    }

    public function test_cancelled_trip_cannot_be_updated(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->cancelled()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/trips/{$trip->id}", ['title' => 'New Title'])
            ->assertStatus(409);
    }

    public function test_ongoing_trip_only_allows_title_and_description_update(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->ongoing()->create(['user_id' => $user->id]);
        $originalDestination = $trip->destination;

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/trips/{$trip->id}", [
                'title'       => 'Updated Title',
                'destination' => 'New Destination', // should be ignored
            ])
            ->assertStatus(200);

        $this->assertSame($originalDestination, $trip->fresh()->destination);
    }

    // ── Resource fields ───────────────────────────────────────────────────────

    public function test_trip_resource_includes_member_count_and_remaining_slots(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/trips', array_merge($this->validPayload(), ['max_members' => 4]))
            ->assertStatus(201)
            ->assertJsonPath('data.trip.member_count', 1)       // owner counts as 1
            ->assertJsonPath('data.trip.remaining_slots', 3);   // 4 - 1 = 3
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    private function validPayload(): array
    {
        return [
            'title'       => 'Udaipur Weekend',
            'destination' => 'Udaipur',
            'start_date'  => now()->addDays(30)->toDateString(),
            'end_date'    => now()->addDays(33)->toDateString(),
            'trip_type'   => TripType::Weekend->value,
            'max_members' => 4,
        ];
    }
}
