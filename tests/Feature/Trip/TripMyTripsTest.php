<?php

namespace Tests\Feature\Trip;

use App\Enums\MemberRole;
use App\Enums\TripType;
use App\Models\Trip;
use App\Models\TripMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TripMyTripsTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_receives_only_own_trips(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        Trip::factory()->count(3)->create(['user_id' => $userA->id]);
        Trip::factory()->count(2)->create(['user_id' => $userB->id]);

        $response = $this->actingAs($userA, 'sanctum')
            ->getJson('/api/my/trips');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data.items');
    }

    public function test_my_trips_returns_paginated_response(): void
    {
        $user = User::factory()->create();
        Trip::factory()->count(5)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/my/trips');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'items',
                    'pagination' => [
                        'total',
                        'per_page',
                        'current_page',
                        'last_page',
                        'has_more',
                    ],
                ],
            ]);
    }

    public function test_unauthenticated_user_cannot_list_trips(): void
    {
        $this->getJson('/api/my/trips')->assertStatus(401);
    }

    public function test_my_trips_returns_empty_when_no_trips(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/my/trips');

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data.items')
            ->assertJsonPath('data.pagination.total', 0);
    }

    public function test_my_trips_includes_member_count(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);

        // Add owner member row to simulate correct setup
        TripMember::factory()->owner()->create(['trip_id' => $trip->id, 'user_id' => $user->id]);
        // Add one extra active member
        TripMember::factory()->create(['trip_id' => $trip->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/my/trips');

        $response->assertStatus(200);
        $memberCount = $response->json('data.items.0.member_count');
        $this->assertSame(2, $memberCount);
    }

    // ── Ownership integrity ────────────────────────────────────────────────────

    public function test_owner_member_row_has_correct_role_and_status(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/trips', [
                'title'       => 'Test Trip',
                'destination' => 'Manali',
                'start_date'  => now()->addDays(10)->toDateString(),
                'end_date'    => now()->addDays(15)->toDateString(),
                'trip_type'   => TripType::Adventure->value,
                'max_members' => 3,
            ])
            ->assertStatus(201);

        $trip   = Trip::where('user_id', $user->id)->first();
        $member = TripMember::where('trip_id', $trip->id)->where('user_id', $user->id)->first();

        $this->assertNotNull($member);
        $this->assertSame(MemberRole::Owner->value, $member->role->value);
        $this->assertNull($member->joined_at);
    }

    public function test_duplicate_owner_membership_cannot_be_created(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);

        // Attempt to create a second owner row for the same user/trip
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        TripMember::factory()->owner()->create(['trip_id' => $trip->id, 'user_id' => $user->id]);
        TripMember::factory()->owner()->create(['trip_id' => $trip->id, 'user_id' => $user->id]);
    }

    // ── Invariant tests ───────────────────────────────────────────────────────

    public function test_remaining_slots_equals_max_members_minus_active_members(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/trips', [
                'title'       => 'Slot Test',
                'destination' => 'Goa',
                'start_date'  => now()->addDays(10)->toDateString(),
                'end_date'    => now()->addDays(15)->toDateString(),
                'trip_type'   => TripType::Beach->value,
                'max_members' => 5,
            ]);

        $response->assertStatus(201);
        $data = $response->json('data.trip');

        $this->assertSame(5, $data['max_members']);
        $this->assertSame(1, $data['member_count']);
        $this->assertSame(4, $data['remaining_slots']);
        $this->assertSame($data['max_members'] - $data['member_count'], $data['remaining_slots']);
    }

    public function test_remaining_slots_is_never_negative(): void
    {
        $user = User::factory()->create();
        // max_members = 2, add owner + 1 member
        $trip = Trip::factory()->create(['user_id' => $user->id, 'max_members' => 2]);
        TripMember::factory()->owner()->create(['trip_id' => $trip->id, 'user_id' => $user->id]);
        TripMember::factory()->create(['trip_id' => $trip->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/trips/{$trip->id}");

        $remaining = $response->json('data.trip.remaining_slots');
        $this->assertGreaterThanOrEqual(0, $remaining);
    }
}
