<?php

namespace Tests\Feature\Trip;

use App\Enums\JoinRequestStatus;
use App\Enums\MemberRole;
use App\Enums\MemberStatus;
use App\Enums\TripStatus;
use App\Models\Trip;
use App\Models\TripJoinRequest;
use App\Models\TripMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TripMemberTest extends TestCase
{
    use RefreshDatabase;

    private function createPublishedTrip(array $attributes = []): Trip
    {
        $owner = $attributes['_owner'] ?? User::factory()->create();
        unset($attributes['_owner']);

        $trip = Trip::factory()->published()->create(array_merge(
            ['user_id' => $owner->id, 'max_members' => 4],
            $attributes,
        ));

        TripMember::factory()->owner()->create([
            'trip_id' => $trip->id,
            'user_id' => $owner->id,
        ]);

        return $trip;
    }

    private function addMember(Trip $trip, User $user, string $status = 'active'): TripMember
    {
        return TripMember::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $user->id,
            'role'    => MemberRole::Member->value,
            'status'  => $status,
        ]);
    }

    // ── Member List API ────────────────────────────────────────────────────────

    public function test_authenticated_user_can_view_active_members(): void
    {
        $trip = $this->createPublishedTrip();
        $member = User::factory()->create();
        $this->addMember($trip, $member);

        $response = $this->actingAs($trip->owner, 'sanctum')
            ->getJson("/api/trips/{$trip->id}/members");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data.members') // Owner + 1 member
            ->assertJsonPath('data.members.0.role', 'owner')
            ->assertJsonPath('data.members.1.role', 'member')
            ->assertJsonPath('data.members.1.user.name', $member->name);
    }

    public function test_guest_cannot_view_members(): void
    {
        $trip = $this->createPublishedTrip();
        $this->getJson("/api/trips/{$trip->id}/members")->assertStatus(401);
    }

    public function test_member_list_excludes_non_active_members_and_requests(): void
    {
        $trip = $this->createPublishedTrip();
        
        // Active member
        $this->addMember($trip, User::factory()->create());
        // Left member
        $this->addMember($trip, User::factory()->create(), MemberStatus::Left->value);
        // Removed member
        $this->addMember($trip, User::factory()->create(), MemberStatus::Removed->value);
        
        // Pending request
        TripJoinRequest::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => User::factory()->create()->id,
            'status' => JoinRequestStatus::Pending->value,
        ]);

        $response = $this->actingAs($trip->owner, 'sanctum')
            ->getJson("/api/trips/{$trip->id}/members");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data.members'); // Only owner + 1 active member
    }

    // ── My Joined Trips API ────────────────────────────────────────────────────

    public function test_user_can_retrieve_their_own_joined_trips(): void
    {
        $user = User::factory()->create();
        
        // 1. Owned trip (should be EXCLUDED)
        $this->createPublishedTrip(['_owner' => $user]);

        // 2. Joined trip (active member) -> INCLUDED
        $joinedTrip = $this->createPublishedTrip();
        $this->addMember($joinedTrip, $user);

        // 3. Left trip -> EXCLUDED
        $leftTrip = $this->createPublishedTrip();
        $this->addMember($leftTrip, $user, MemberStatus::Left->value);

        // 4. Pending request trip -> EXCLUDED
        $pendingTrip = $this->createPublishedTrip();
        TripJoinRequest::factory()->create([
            'trip_id' => $pendingTrip->id,
            'user_id' => $user->id,
            'status' => JoinRequestStatus::Pending->value,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/my/joined-trips");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $joinedTrip->id)
            ->assertJsonStructure([
                'data' => [
                    'items',
                    'pagination' => [
                        'total', 'per_page', 'current_page', 'last_page', 'has_more'
                    ],
                ]
            ]);
    }

    // ── TripResource Membership Context ────────────────────────────────────────

    public function test_trip_resource_membership_shows_owner_correctly(): void
    {
        $user = User::factory()->create();
        $trip = $this->createPublishedTrip(['_owner' => $user]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/trips/{$trip->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.trip.membership.is_owner', true)
            ->assertJsonPath('data.trip.membership.is_member', false)
            ->assertJsonPath('data.trip.membership.role', 'owner')
            ->assertJsonPath('data.trip.membership.status', 'active');
    }

    public function test_trip_resource_membership_shows_active_member_correctly(): void
    {
        $user = User::factory()->create();
        $trip = $this->createPublishedTrip();
        $this->addMember($trip, $user);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/trips/{$trip->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.trip.membership.is_owner', false)
            ->assertJsonPath('data.trip.membership.is_member', true)
            ->assertJsonPath('data.trip.membership.role', 'member')
            ->assertJsonPath('data.trip.membership.status', 'active');
    }

    public function test_trip_resource_membership_shows_pending_request(): void
    {
        $user = User::factory()->create();
        $trip = $this->createPublishedTrip();
        TripJoinRequest::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $user->id,
            'status' => JoinRequestStatus::Pending->value,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/trips/{$trip->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.trip.membership.is_owner', false)
            ->assertJsonPath('data.trip.membership.is_member', false)
            ->assertJsonPath('data.trip.membership.join_request_status', 'pending');
    }

    public function test_trip_resource_membership_shows_non_member(): void
    {
        $user = User::factory()->create();
        $trip = $this->createPublishedTrip();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/trips/{$trip->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.trip.membership.is_owner', false)
            ->assertJsonPath('data.trip.membership.is_member', false)
            ->assertJsonPath('data.trip.membership.role', null)
            ->assertJsonPath('data.trip.membership.join_request_status', null);
    }
}
