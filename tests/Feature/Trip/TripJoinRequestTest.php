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

/**
 * Feature tests for D.1: Trip Join Request & Companion Backend.
 *
 * Coverage:
 *   - Submit join request (happy path + all failure modes)
 *   - View join requests (owner only)
 *   - Approve (owner only, transactional, capacity-guarded)
 *   - Reject (owner only, pending only)
 *   - Cancel (requester only, pending only)
 *   - API response structure
 *   - Backward-compat: existing TripResource fields unaffected
 */
class TripJoinRequestTest extends TestCase
{
    use RefreshDatabase;

    // ════════════════════════════════════════════════════════════════════════════
    // Helpers
    // ════════════════════════════════════════════════════════════════════════════

    /**
     * Create a published trip with its owner's trip_members row.
     * TripService normally handles this atomically; we replicate it here.
     */
    private function createPublishedTrip(array $attributes = []): Trip
    {
        $owner = $attributes['_owner'] ?? User::factory()->create();
        unset($attributes['_owner']);

        $trip = Trip::factory()->published()->create(array_merge(
            ['user_id' => $owner->id, 'max_members' => 4],
            $attributes,
        ));

        // Create owner's membership row (mirrors TripService::createTrip)
        TripMember::factory()->owner()->create([
            'trip_id' => $trip->id,
            'user_id' => $owner->id,
        ]);

        return $trip;
    }

    /**
     * Create a member-user and their TripMember row for a given trip.
     */
    private function addMember(Trip $trip, User $user): TripMember
    {
        return TripMember::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $user->id,
            'role'    => MemberRole::Member->value,
            'status'  => MemberStatus::Active->value,
        ]);
    }

    private function joinUrl(Trip $trip): string
    {
        return "/api/trips/{$trip->id}/join-requests";
    }

    private function approveUrl(Trip $trip, TripJoinRequest $jr): string
    {
        return "/api/trips/{$trip->id}/join-requests/{$jr->id}/approve";
    }

    private function rejectUrl(Trip $trip, TripJoinRequest $jr): string
    {
        return "/api/trips/{$trip->id}/join-requests/{$jr->id}/reject";
    }

    private function cancelUrl(Trip $trip, TripJoinRequest $jr): string
    {
        return "/api/trips/{$trip->id}/join-requests/{$jr->id}/cancel";
    }

    // ════════════════════════════════════════════════════════════════════════════
    // Test 1 — Authenticated user can request to join a published trip
    // ════════════════════════════════════════════════════════════════════════════

    public function test_authenticated_user_can_request_to_join_published_trip(): void
    {
        $requester = User::factory()->create();
        $trip      = $this->createPublishedTrip();

        $response = $this->actingAs($requester, 'sanctum')
            ->postJson($this->joinUrl($trip));

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.join_request.status', 'pending')
            ->assertJsonPath('data.join_request.trip_id', $trip->id)
            ->assertJsonPath('data.join_request.requester.id', $requester->id);

        $this->assertDatabaseHas('trip_join_requests', [
            'trip_id' => $trip->id,
            'user_id' => $requester->id,
            'status'  => 'pending',
        ]);
    }

    // ════════════════════════════════════════════════════════════════════════════
    // Test 2 — Guest cannot request
    // ════════════════════════════════════════════════════════════════════════════

    public function test_guest_cannot_submit_join_request(): void
    {
        $trip = $this->createPublishedTrip();

        $this->postJson($this->joinUrl($trip))->assertStatus(401);
    }

    // ════════════════════════════════════════════════════════════════════════════
    // Test 3 — Owner cannot request own trip
    // ════════════════════════════════════════════════════════════════════════════

    public function test_owner_cannot_request_to_join_own_trip(): void
    {
        $owner = User::factory()->create();
        $trip  = $this->createPublishedTrip(['_owner' => $owner]);

        $this->actingAs($owner, 'sanctum')
            ->postJson($this->joinUrl($trip))
            ->assertStatus(403);
    }

    // ════════════════════════════════════════════════════════════════════════════
    // Test 4 — Cannot request draft trip
    // ════════════════════════════════════════════════════════════════════════════

    public function test_cannot_request_draft_trip(): void
    {
        $owner     = User::factory()->create();
        $requester = User::factory()->create();
        $trip      = Trip::factory()->create(['user_id' => $owner->id, 'status' => TripStatus::Draft->value]);

        $this->actingAs($requester, 'sanctum')
            ->postJson($this->joinUrl($trip))
            ->assertStatus(403);
    }

    // ════════════════════════════════════════════════════════════════════════════
    // Test 5 — Cannot request completed trip
    // ════════════════════════════════════════════════════════════════════════════

    public function test_cannot_request_completed_trip(): void
    {
        $owner     = User::factory()->create();
        $requester = User::factory()->create();
        $trip      = Trip::factory()->completed()->create(['user_id' => $owner->id]);

        $this->actingAs($requester, 'sanctum')
            ->postJson($this->joinUrl($trip))
            ->assertStatus(403);
    }

    // ════════════════════════════════════════════════════════════════════════════
    // Test 6 — Cannot request cancelled trip
    // ════════════════════════════════════════════════════════════════════════════

    public function test_cannot_request_cancelled_trip(): void
    {
        $owner     = User::factory()->create();
        $requester = User::factory()->create();
        $trip      = Trip::factory()->cancelled()->create(['user_id' => $owner->id]);

        $this->actingAs($requester, 'sanctum')
            ->postJson($this->joinUrl($trip))
            ->assertStatus(403);
    }

    // ════════════════════════════════════════════════════════════════════════════
    // Test 7 — Cannot request ongoing trip
    // ════════════════════════════════════════════════════════════════════════════

    public function test_cannot_request_ongoing_trip(): void
    {
        $owner     = User::factory()->create();
        $requester = User::factory()->create();
        $trip      = Trip::factory()->ongoing()->create(['user_id' => $owner->id]);

        $this->actingAs($requester, 'sanctum')
            ->postJson($this->joinUrl($trip))
            ->assertStatus(403);
    }

    // ════════════════════════════════════════════════════════════════════════════
    // Test 8 — Cannot request past published trip (end_date in past)
    // ════════════════════════════════════════════════════════════════════════════

    public function test_cannot_request_past_published_trip(): void
    {
        $owner     = User::factory()->create();
        $requester = User::factory()->create();

        $trip = Trip::factory()->published()->create([
            'user_id'    => $owner->id,
            'start_date' => now()->subDays(10)->toDateString(),
            'end_date'   => now()->subDays(3)->toDateString(),
        ]);

        TripMember::factory()->owner()->create(['trip_id' => $trip->id, 'user_id' => $owner->id]);

        $this->actingAs($requester, 'sanctum')
            ->postJson($this->joinUrl($trip))
            ->assertStatus(409);
    }

    // ════════════════════════════════════════════════════════════════════════════
    // Test 9 — Cannot request when trip is full
    // ════════════════════════════════════════════════════════════════════════════

    public function test_cannot_request_when_trip_is_full(): void
    {
        $owner     = User::factory()->create();
        $requester = User::factory()->create();
        $trip      = $this->createPublishedTrip(['_owner' => $owner, 'max_members' => 2]);

        // Trip has max_members=2 (owner already occupies 1 slot); add 1 more member to fill it
        $this->addMember($trip, User::factory()->create());

        $this->actingAs($requester, 'sanctum')
            ->postJson($this->joinUrl($trip))
            ->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    // ════════════════════════════════════════════════════════════════════════════
    // Test 10 — Duplicate pending request is rejected
    // ════════════════════════════════════════════════════════════════════════════

    public function test_duplicate_pending_request_is_rejected(): void
    {
        $requester = User::factory()->create();
        $trip      = $this->createPublishedTrip();

        TripJoinRequest::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $requester->id,
            'status'  => JoinRequestStatus::Pending->value,
        ]);

        $this->actingAs($requester, 'sanctum')
            ->postJson($this->joinUrl($trip))
            ->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    // ════════════════════════════════════════════════════════════════════════════
    // Test 11 — User with previously rejected request can submit fresh request
    // ════════════════════════════════════════════════════════════════════════════

    public function test_user_can_resubmit_after_rejected_request(): void
    {
        $requester = User::factory()->create();
        $trip      = $this->createPublishedTrip();

        // Old rejected request exists
        TripJoinRequest::factory()->rejected()->create([
            'trip_id' => $trip->id,
            'user_id' => $requester->id,
        ]);

        $this->actingAs($requester, 'sanctum')
            ->postJson($this->joinUrl($trip))
            ->assertStatus(201)
            ->assertJsonPath('data.join_request.status', 'pending');

        $this->assertDatabaseCount('trip_join_requests', 2);
    }

    // ════════════════════════════════════════════════════════════════════════════
    // Test 12 — User with previously cancelled request can submit fresh request
    // ════════════════════════════════════════════════════════════════════════════

    public function test_user_can_resubmit_after_cancelled_request(): void
    {
        $requester = User::factory()->create();
        $trip      = $this->createPublishedTrip();

        TripJoinRequest::factory()->cancelled()->create([
            'trip_id' => $trip->id,
            'user_id' => $requester->id,
        ]);

        $this->actingAs($requester, 'sanctum')
            ->postJson($this->joinUrl($trip))
            ->assertStatus(201)
            ->assertJsonPath('data.join_request.status', 'pending');
    }

    // ════════════════════════════════════════════════════════════════════════════
    // Test 13 — Existing active member cannot submit a join request
    // ════════════════════════════════════════════════════════════════════════════

    public function test_existing_active_member_cannot_request(): void
    {
        $owner  = User::factory()->create();
        $member = User::factory()->create();
        $trip   = $this->createPublishedTrip(['_owner' => $owner]);

        $this->addMember($trip, $member);

        $this->actingAs($member, 'sanctum')
            ->postJson($this->joinUrl($trip))
            ->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    // ════════════════════════════════════════════════════════════════════════════
    // Test 14 — Only trip owner can view join requests
    // ════════════════════════════════════════════════════════════════════════════

    public function test_owner_can_view_join_requests(): void
    {
        $owner     = User::factory()->create();
        $requester = User::factory()->create();
        $trip      = $this->createPublishedTrip(['_owner' => $owner]);

        TripJoinRequest::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $requester->id,
        ]);

        $this->actingAs($owner, 'sanctum')
            ->getJson($this->joinUrl($trip))
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.join_requests');
    }

    // ════════════════════════════════════════════════════════════════════════════
    // Test 15 — Non-owner cannot view join requests
    // ════════════════════════════════════════════════════════════════════════════

    public function test_non_owner_cannot_view_join_requests(): void
    {
        $owner     = User::factory()->create();
        $stranger  = User::factory()->create();
        $trip      = $this->createPublishedTrip(['_owner' => $owner]);

        $this->actingAs($stranger, 'sanctum')
            ->getJson($this->joinUrl($trip))
            ->assertStatus(403);
    }

    // ════════════════════════════════════════════════════════════════════════════
    // Test 16 — Only trip owner can approve
    // ════════════════════════════════════════════════════════════════════════════

    public function test_only_trip_owner_can_approve(): void
    {
        $owner     = User::factory()->create();
        $requester = User::factory()->create();
        $stranger  = User::factory()->create();
        $trip      = $this->createPublishedTrip(['_owner' => $owner]);

        $jr = TripJoinRequest::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $requester->id,
        ]);

        // Stranger is rejected
        $this->actingAs($stranger, 'sanctum')
            ->postJson($this->approveUrl($trip, $jr))
            ->assertStatus(403);

        // Owner succeeds
        $this->actingAs($owner, 'sanctum')
            ->postJson($this->approveUrl($trip, $jr))
            ->assertStatus(200);
    }

    // ════════════════════════════════════════════════════════════════════════════
    // Test 17 — Only trip owner can reject
    // ════════════════════════════════════════════════════════════════════════════

    public function test_only_trip_owner_can_reject(): void
    {
        $owner     = User::factory()->create();
        $requester = User::factory()->create();
        $stranger  = User::factory()->create();
        $trip      = $this->createPublishedTrip(['_owner' => $owner]);

        $jr = TripJoinRequest::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $requester->id,
        ]);

        $this->actingAs($stranger, 'sanctum')
            ->postJson($this->rejectUrl($trip, $jr))
            ->assertStatus(403);

        $this->actingAs($owner, 'sanctum')
            ->postJson($this->rejectUrl($trip, $jr))
            ->assertStatus(200);
    }

    // ════════════════════════════════════════════════════════════════════════════
    // Test 18 — Requester can cancel own pending request
    // ════════════════════════════════════════════════════════════════════════════

    public function test_requester_can_cancel_own_pending_request(): void
    {
        $requester = User::factory()->create();
        $trip      = $this->createPublishedTrip();

        $jr = TripJoinRequest::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $requester->id,
        ]);

        $this->actingAs($requester, 'sanctum')
            ->postJson($this->cancelUrl($trip, $jr))
            ->assertStatus(200)
            ->assertJsonPath('data.join_request.status', 'cancelled');
    }

    // ════════════════════════════════════════════════════════════════════════════
    // Test 19 — User cannot cancel another user's request
    // ════════════════════════════════════════════════════════════════════════════

    public function test_user_cannot_cancel_another_users_request(): void
    {
        $requester = User::factory()->create();
        $stranger  = User::factory()->create();
        $trip      = $this->createPublishedTrip();

        $jr = TripJoinRequest::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $requester->id,
        ]);

        $this->actingAs($stranger, 'sanctum')
            ->postJson($this->cancelUrl($trip, $jr))
            ->assertStatus(403);
    }

    // ════════════════════════════════════════════════════════════════════════════
    // Test 20 — Approving creates a trip_members row
    // ════════════════════════════════════════════════════════════════════════════

    public function test_approving_creates_a_trip_member(): void
    {
        $owner     = User::factory()->create();
        $requester = User::factory()->create();
        $trip      = $this->createPublishedTrip(['_owner' => $owner]);

        $jr = TripJoinRequest::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $requester->id,
        ]);

        $this->actingAs($owner, 'sanctum')
            ->postJson($this->approveUrl($trip, $jr))
            ->assertStatus(200);

        $this->assertDatabaseHas('trip_members', [
            'trip_id' => $trip->id,
            'user_id' => $requester->id,
            'role'    => MemberRole::Member->value,
            'status'  => MemberStatus::Active->value,
        ]);
    }

    // ════════════════════════════════════════════════════════════════════════════
    // Test 21 — Approving changes request status to approved
    // ════════════════════════════════════════════════════════════════════════════

    public function test_approving_changes_request_status_to_approved(): void
    {
        $owner     = User::factory()->create();
        $requester = User::factory()->create();
        $trip      = $this->createPublishedTrip(['_owner' => $owner]);

        $jr = TripJoinRequest::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $requester->id,
        ]);

        $this->actingAs($owner, 'sanctum')
            ->postJson($this->approveUrl($trip, $jr))
            ->assertStatus(200)
            ->assertJsonPath('data.join_request.status', 'approved');

        $this->assertDatabaseHas('trip_join_requests', [
            'id'     => $jr->id,
            'status' => 'approved',
        ]);
    }

    // ════════════════════════════════════════════════════════════════════════════
    // Test 22 — Rejecting does NOT create a trip member
    // ════════════════════════════════════════════════════════════════════════════

    public function test_rejecting_does_not_create_trip_member(): void
    {
        $owner     = User::factory()->create();
        $requester = User::factory()->create();
        $trip      = $this->createPublishedTrip(['_owner' => $owner]);

        $jr = TripJoinRequest::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $requester->id,
        ]);

        $this->actingAs($owner, 'sanctum')
            ->postJson($this->rejectUrl($trip, $jr))
            ->assertStatus(200)
            ->assertJsonPath('data.join_request.status', 'rejected');

        $this->assertDatabaseMissing('trip_members', [
            'trip_id' => $trip->id,
            'user_id' => $requester->id,
            'role'    => MemberRole::Member->value,
        ]);
    }

    // ════════════════════════════════════════════════════════════════════════════
    // Test 23 — Cancelling does NOT create a trip member
    // ════════════════════════════════════════════════════════════════════════════

    public function test_cancelling_does_not_create_trip_member(): void
    {
        $requester = User::factory()->create();
        $trip      = $this->createPublishedTrip();

        $jr = TripJoinRequest::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $requester->id,
        ]);

        $this->actingAs($requester, 'sanctum')
            ->postJson($this->cancelUrl($trip, $jr))
            ->assertStatus(200);

        $this->assertDatabaseMissing('trip_members', [
            'trip_id' => $trip->id,
            'user_id' => $requester->id,
            'role'    => MemberRole::Member->value,
        ]);
    }

    // ════════════════════════════════════════════════════════════════════════════
    // Test 24 — Cannot approve an already rejected request
    // ════════════════════════════════════════════════════════════════════════════

    public function test_cannot_approve_already_rejected_request(): void
    {
        $owner     = User::factory()->create();
        $requester = User::factory()->create();
        $trip      = $this->createPublishedTrip(['_owner' => $owner]);

        $jr = TripJoinRequest::factory()->rejected()->create([
            'trip_id' => $trip->id,
            'user_id' => $requester->id,
        ]);

        $this->actingAs($owner, 'sanctum')
            ->postJson($this->approveUrl($trip, $jr))
            ->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    // ════════════════════════════════════════════════════════════════════════════
    // Test 25 — Cannot approve an already cancelled request
    // ════════════════════════════════════════════════════════════════════════════

    public function test_cannot_approve_already_cancelled_request(): void
    {
        $owner     = User::factory()->create();
        $requester = User::factory()->create();
        $trip      = $this->createPublishedTrip(['_owner' => $owner]);

        $jr = TripJoinRequest::factory()->cancelled()->create([
            'trip_id' => $trip->id,
            'user_id' => $requester->id,
        ]);

        $this->actingAs($owner, 'sanctum')
            ->postJson($this->approveUrl($trip, $jr))
            ->assertStatus(409);
    }

    // ════════════════════════════════════════════════════════════════════════════
    // Test 26 — Cannot reject an already approved request
    // ════════════════════════════════════════════════════════════════════════════

    public function test_cannot_reject_already_approved_request(): void
    {
        $owner     = User::factory()->create();
        $requester = User::factory()->create();
        $trip      = $this->createPublishedTrip(['_owner' => $owner]);

        $jr = TripJoinRequest::factory()->approved()->create([
            'trip_id' => $trip->id,
            'user_id' => $requester->id,
        ]);

        $this->actingAs($owner, 'sanctum')
            ->postJson($this->rejectUrl($trip, $jr))
            ->assertStatus(409);
    }

    // ════════════════════════════════════════════════════════════════════════════
    // Test 27 — Cannot cancel an already approved request
    // ════════════════════════════════════════════════════════════════════════════

    public function test_cannot_cancel_already_approved_request(): void
    {
        $requester = User::factory()->create();
        $trip      = $this->createPublishedTrip();

        $jr = TripJoinRequest::factory()->approved()->create([
            'trip_id' => $trip->id,
            'user_id' => $requester->id,
        ]);

        $this->actingAs($requester, 'sanctum')
            ->postJson($this->cancelUrl($trip, $jr))
            ->assertStatus(409);
    }

    // ════════════════════════════════════════════════════════════════════════════
    // Test 28 — Membership is unique (no duplicate trip_members rows)
    // ════════════════════════════════════════════════════════════════════════════

    public function test_membership_is_unique(): void
    {
        $owner     = User::factory()->create();
        $requester = User::factory()->create();
        $trip      = $this->createPublishedTrip(['_owner' => $owner]);

        $jr = TripJoinRequest::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $requester->id,
        ]);

        $this->actingAs($owner, 'sanctum')
            ->postJson($this->approveUrl($trip, $jr))
            ->assertStatus(200);

        $this->assertEquals(1, TripMember::where('trip_id', $trip->id)
            ->where('user_id', $requester->id)
            ->count());
    }

    // ════════════════════════════════════════════════════════════════════════════
    // Test 29 — Approval respects max_members capacity guard
    // ════════════════════════════════════════════════════════════════════════════

    public function test_approval_cannot_exceed_max_members(): void
    {
        $owner      = User::factory()->create();
        $requester1 = User::factory()->create();
        $requester2 = User::factory()->create();

        // max_members=2 → owner occupies 1 slot → only 1 more allowed
        $trip = $this->createPublishedTrip(['_owner' => $owner, 'max_members' => 2]);

        $jr1 = TripJoinRequest::factory()->create(['trip_id' => $trip->id, 'user_id' => $requester1->id]);
        $jr2 = TripJoinRequest::factory()->create(['trip_id' => $trip->id, 'user_id' => $requester2->id]);

        // Approve first request — fills the last slot
        $this->actingAs($owner, 'sanctum')
            ->postJson($this->approveUrl($trip, $jr1))
            ->assertStatus(200);

        // Approve second request — should fail (trip now full)
        $this->actingAs($owner, 'sanctum')
            ->postJson($this->approveUrl($trip, $jr2))
            ->assertStatus(409)
            ->assertJsonPath('success', false);

        // Exactly 2 members total (owner + requester1)
        $this->assertEquals(2, TripMember::where('trip_id', $trip->id)
            ->where('status', MemberStatus::Active->value)
            ->count());
    }

    // ════════════════════════════════════════════════════════════════════════════
    // Test 30 — Request for wrong trip returns 404
    // ════════════════════════════════════════════════════════════════════════════

    public function test_join_request_from_different_trip_returns_404(): void
    {
        $owner     = User::factory()->create();
        $requester = User::factory()->create();
        $trip1     = $this->createPublishedTrip(['_owner' => $owner]);
        $trip2     = $this->createPublishedTrip(['_owner' => $owner]);

        // Create request on trip2
        $jr = TripJoinRequest::factory()->create([
            'trip_id' => $trip2->id,
            'user_id' => $requester->id,
        ]);

        // Try to approve it under trip1's URL → 404
        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/trips/{$trip1->id}/join-requests/{$jr->id}/approve")
            ->assertStatus(404);
    }

    // ════════════════════════════════════════════════════════════════════════════
    // Test 31 — API response structure is correct
    // ════════════════════════════════════════════════════════════════════════════

    public function test_join_request_response_structure_is_correct(): void
    {
        $requester = User::factory()->create();
        $trip      = $this->createPublishedTrip();

        $response = $this->actingAs($requester, 'sanctum')
            ->postJson($this->joinUrl($trip));

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'join_request' => [
                        'id',
                        'trip_id',
                        'status',
                        'requester' => ['id', 'name'],
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);
    }

    // ════════════════════════════════════════════════════════════════════════════
    // Test 32 — Backward compat: existing TripResource fields unaffected
    // ════════════════════════════════════════════════════════════════════════════

    public function test_existing_trip_resource_fields_are_unaffected(): void
    {
        $owner = User::factory()->create();
        $trip  = $this->createPublishedTrip(['_owner' => $owner]);

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/trips/{$trip->id}")
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'trip' => [
                        'id', 'title', 'destination', 'place_id',
                        'latitude', 'longitude', 'start_date', 'end_date',
                        'budget_min', 'budget_max', 'trip_type', 'description',
                        'max_members', 'status',
                        'owner'    => ['id', 'name'],
                        'interests',
                        'member_count', 'remaining_slots',
                        'created_at', 'updated_at',
                    ],
                ],
            ]);
    }

    // ════════════════════════════════════════════════════════════════════════════
    // Test 33 — member_count / remaining_slots correct after approval
    // ════════════════════════════════════════════════════════════════════════════

    public function test_member_count_and_remaining_slots_update_after_approval(): void
    {
        $owner     = User::factory()->create();
        $requester = User::factory()->create();

        // max_members=4: owner(1) + 3 slots available
        $trip = $this->createPublishedTrip(['_owner' => $owner, 'max_members' => 4]);

        $jr = TripJoinRequest::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $requester->id,
        ]);

        // Before approval
        $resp = $this->actingAs($owner, 'sanctum')->getJson("/api/trips/{$trip->id}");
        $resp->assertJsonPath('data.trip.member_count', 1)
             ->assertJsonPath('data.trip.remaining_slots', 3);

        // Approve
        $this->actingAs($owner, 'sanctum')
            ->postJson($this->approveUrl($trip, $jr))
            ->assertStatus(200);

        // After approval
        $resp = $this->actingAs($owner, 'sanctum')->getJson("/api/trips/{$trip->id}");
        $resp->assertJsonPath('data.trip.member_count', 2)
             ->assertJsonPath('data.trip.remaining_slots', 2);
    }
}
