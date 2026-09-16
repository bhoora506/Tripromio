<?php

namespace Tests\Feature\Connection;

use App\Enums\ConnectionStatus;
use App\Models\ConnectionRequest;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F2 API Tests — Connection Request Endpoints.
 *
 * Covers all 29 scenarios specified in the F2 requirements.
 */
class ConnectionRequestApiTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Create a user with a discoverable profile (is_discoverable = true).
     */
    private function discoverableUser(): User
    {
        $user = User::factory()->create();
        UserProfile::create([
            'user_id'         => $user->id,
            'is_discoverable' => true,
        ]);
        return $user;
    }

    /**
     * Create a user with a non-discoverable profile (is_discoverable = false).
     */
    private function hiddenUser(): User
    {
        $user = User::factory()->create();
        UserProfile::create([
            'user_id'         => $user->id,
            'is_discoverable' => false,
        ]);
        return $user;
    }

    private function sendUrl(): string
    {
        return '/api/connections';
    }

    private function receivedUrl(): string
    {
        return '/api/connections/received';
    }

    private function sentUrl(): string
    {
        return '/api/connections/sent';
    }

    private function acceptUrl(ConnectionRequest $cr): string
    {
        return "/api/connections/{$cr->id}/accept";
    }

    private function rejectUrl(ConnectionRequest $cr): string
    {
        return "/api/connections/{$cr->id}/reject";
    }

    private function cancelUrl(ConnectionRequest $cr): string
    {
        return "/api/connections/{$cr->id}/cancel";
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 1. Authenticated user can send a connection request
    // ══════════════════════════════════════════════════════════════════════════

    public function test_authenticated_user_can_send_connection_request(): void
    {
        $requester = $this->discoverableUser();
        $recipient = $this->discoverableUser();

        $response = $this->actingAs($requester, 'sanctum')
            ->postJson($this->sendUrl(), ['recipient_id' => $recipient->id]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.connection_request.status', 'pending')
            ->assertJsonPath('data.connection_request.requester.id', $requester->id)
            ->assertJsonPath('data.connection_request.recipient.id', $recipient->id);

        $this->assertDatabaseHas('connection_requests', [
            'requester_id' => $requester->id,
            'recipient_id' => $recipient->id,
            'status'       => 'pending',
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 2. Unauthenticated user cannot send a request
    // ══════════════════════════════════════════════════════════════════════════

    public function test_unauthenticated_user_cannot_send_request(): void
    {
        $recipient = $this->discoverableUser();

        $this->postJson($this->sendUrl(), ['recipient_id' => $recipient->id])
            ->assertStatus(401);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 3. Self-request is rejected with 409
    // ══════════════════════════════════════════════════════════════════════════

    public function test_self_request_is_rejected(): void
    {
        $user = $this->discoverableUser();

        $this->actingAs($user, 'sanctum')
            ->postJson($this->sendUrl(), ['recipient_id' => $user->id])
            ->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 4. Non-existent recipient returns 422
    // ══════════════════════════════════════════════════════════════════════════

    public function test_non_existent_recipient_fails_validation(): void
    {
        $requester = $this->discoverableUser();

        $this->actingAs($requester, 'sanctum')
            ->postJson($this->sendUrl(), ['recipient_id' => 99999])
            ->assertStatus(422);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 5. Non-discoverable recipient returns 404
    // ══════════════════════════════════════════════════════════════════════════

    public function test_non_discoverable_recipient_returns_404(): void
    {
        $requester = $this->discoverableUser();
        $hidden    = $this->hiddenUser();

        $this->actingAs($requester, 'sanctum')
            ->postJson($this->sendUrl(), ['recipient_id' => $hidden->id])
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 6. Recipient with no profile at all returns 404
    // ══════════════════════════════════════════════════════════════════════════

    public function test_recipient_with_no_profile_returns_404(): void
    {
        $requester  = $this->discoverableUser();
        $noProfile  = User::factory()->create(); // no UserProfile row

        $this->actingAs($requester, 'sanctum')
            ->postJson($this->sendUrl(), ['recipient_id' => $noProfile->id])
            ->assertStatus(404);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 7. Duplicate pending request is rejected with 409
    // ══════════════════════════════════════════════════════════════════════════

    public function test_duplicate_pending_request_rejected(): void
    {
        $requester = $this->discoverableUser();
        $recipient = $this->discoverableUser();

        ConnectionRequest::factory()->create([
            'requester_id' => $requester->id,
            'recipient_id' => $recipient->id,
            'status'       => ConnectionStatus::Pending->value,
        ]);

        $this->actingAs($requester, 'sanctum')
            ->postJson($this->sendUrl(), ['recipient_id' => $recipient->id])
            ->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 8. Opposite-direction pending request is also rejected with 409
    // ══════════════════════════════════════════════════════════════════════════

    public function test_opposite_direction_pending_request_rejected(): void
    {
        $a = $this->discoverableUser();
        $b = $this->discoverableUser();

        // B → A pending already exists
        ConnectionRequest::factory()->create([
            'requester_id' => $b->id,
            'recipient_id' => $a->id,
            'status'       => ConnectionStatus::Pending->value,
        ]);

        // A → B should fail because B already has a pending request to A
        $this->actingAs($a, 'sanctum')
            ->postJson($this->sendUrl(), ['recipient_id' => $b->id])
            ->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 9. Existing accepted connection blocks new request (same direction)
    // ══════════════════════════════════════════════════════════════════════════

    public function test_accepted_connection_blocks_new_request(): void
    {
        $a = $this->discoverableUser();
        $b = $this->discoverableUser();

        ConnectionRequest::factory()->accepted()->create([
            'requester_id' => $a->id,
            'recipient_id' => $b->id,
        ]);

        $this->actingAs($a, 'sanctum')
            ->postJson($this->sendUrl(), ['recipient_id' => $b->id])
            ->assertStatus(409);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 10. Existing accepted connection blocks new request (opposite direction)
    // ══════════════════════════════════════════════════════════════════════════

    public function test_accepted_connection_blocks_reverse_new_request(): void
    {
        $a = $this->discoverableUser();
        $b = $this->discoverableUser();

        ConnectionRequest::factory()->accepted()->create([
            'requester_id' => $b->id,
            'recipient_id' => $a->id,
        ]);

        $this->actingAs($a, 'sanctum')
            ->postJson($this->sendUrl(), ['recipient_id' => $b->id])
            ->assertStatus(409);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 11. Rejected request can be sent again
    // ══════════════════════════════════════════════════════════════════════════

    public function test_rejected_request_allows_new_request(): void
    {
        $a = $this->discoverableUser();
        $b = $this->discoverableUser();

        ConnectionRequest::factory()->rejected()->create([
            'requester_id' => $a->id,
            'recipient_id' => $b->id,
        ]);

        $this->actingAs($a, 'sanctum')
            ->postJson($this->sendUrl(), ['recipient_id' => $b->id])
            ->assertStatus(201)
            ->assertJsonPath('data.connection_request.status', 'pending');

        $this->assertDatabaseCount('connection_requests', 2);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 12. Cancelled request can be sent again
    // ══════════════════════════════════════════════════════════════════════════

    public function test_cancelled_request_allows_new_request(): void
    {
        $a = $this->discoverableUser();
        $b = $this->discoverableUser();

        ConnectionRequest::factory()->cancelled()->create([
            'requester_id' => $a->id,
            'recipient_id' => $b->id,
        ]);

        $this->actingAs($a, 'sanctum')
            ->postJson($this->sendUrl(), ['recipient_id' => $b->id])
            ->assertStatus(201)
            ->assertJsonPath('data.connection_request.status', 'pending');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 13. Received list returns only requests received by authenticated user
    // ══════════════════════════════════════════════════════════════════════════

    public function test_received_list_returns_only_received_requests(): void
    {
        $a = $this->discoverableUser();
        $b = $this->discoverableUser();
        $c = $this->discoverableUser();

        // A receives 2 requests (from B and C)
        ConnectionRequest::factory()->create(['requester_id' => $b->id, 'recipient_id' => $a->id]);
        ConnectionRequest::factory()->create(['requester_id' => $c->id, 'recipient_id' => $a->id]);

        // A sends 1 request (should NOT appear in received)
        ConnectionRequest::factory()->create(['requester_id' => $a->id, 'recipient_id' => $b->id]);

        $this->actingAs($a, 'sanctum')
            ->getJson($this->receivedUrl())
            ->assertStatus(200)
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('success', true);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 14. Sent list returns only requests sent by authenticated user
    // ══════════════════════════════════════════════════════════════════════════

    public function test_sent_list_returns_only_sent_requests(): void
    {
        $a = $this->discoverableUser();
        $b = $this->discoverableUser();
        $c = $this->discoverableUser();

        // A sends 2 requests
        ConnectionRequest::factory()->create(['requester_id' => $a->id, 'recipient_id' => $b->id]);
        ConnectionRequest::factory()->create(['requester_id' => $a->id, 'recipient_id' => $c->id]);

        // A receives 1 (should NOT appear in sent)
        ConnectionRequest::factory()->create(['requester_id' => $b->id, 'recipient_id' => $a->id]);

        $this->actingAs($a, 'sanctum')
            ->getJson($this->sentUrl())
            ->assertStatus(200)
            ->assertJsonCount(2, 'data.items');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 15. Unauthenticated access to received/sent is blocked
    // ══════════════════════════════════════════════════════════════════════════

    public function test_unauthenticated_cannot_access_received_or_sent(): void
    {
        $this->getJson($this->receivedUrl())->assertStatus(401);
        $this->getJson($this->sentUrl())->assertStatus(401);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 16. Pagination works and metadata is present
    // ══════════════════════════════════════════════════════════════════════════

    public function test_pagination_metadata_is_present(): void
    {
        $a = $this->discoverableUser();

        for ($i = 0; $i < 3; $i++) {
            $sender = $this->discoverableUser();
            ConnectionRequest::factory()->create(['requester_id' => $sender->id, 'recipient_id' => $a->id]);
        }

        $this->actingAs($a, 'sanctum')
            ->getJson($this->receivedUrl() . '?per_page=2')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'items',
                    'pagination' => ['total', 'per_page', 'current_page', 'last_page', 'has_more'],
                ],
            ])
            ->assertJsonPath('data.pagination.total', 3)
            ->assertJsonPath('data.pagination.per_page', 2)
            ->assertJsonPath('data.pagination.has_more', true);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 17. Newest requests are returned first
    // ══════════════════════════════════════════════════════════════════════════

    public function test_received_list_is_ordered_newest_first(): void
    {
        $recipient = $this->discoverableUser();

        $first  = ConnectionRequest::factory()->create([
            'recipient_id' => $recipient->id,
            'created_at'   => now()->subMinutes(10),
        ]);
        $second = ConnectionRequest::factory()->create([
            'recipient_id' => $recipient->id,
            'created_at'   => now()->subMinutes(5),
        ]);
        $third = ConnectionRequest::factory()->create([
            'recipient_id' => $recipient->id,
            'created_at'   => now(),
        ]);

        $this->actingAs($recipient, 'sanctum')
            ->getJson($this->receivedUrl())
            ->assertStatus(200)
            ->assertJsonPath('data.items.0.id', $third->id)
            ->assertJsonPath('data.items.1.id', $second->id)
            ->assertJsonPath('data.items.2.id', $first->id);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 18. Recipient can accept a pending request
    // ══════════════════════════════════════════════════════════════════════════

    public function test_recipient_can_accept_pending_request(): void
    {
        $a = $this->discoverableUser();
        $b = $this->discoverableUser();
        $cr = ConnectionRequest::factory()->create(['requester_id' => $a->id, 'recipient_id' => $b->id]);

        $this->actingAs($b, 'sanctum')
            ->postJson($this->acceptUrl($cr))
            ->assertStatus(200)
            ->assertJsonPath('data.connection_request.status', 'accepted');

        $this->assertDatabaseHas('connection_requests', ['id' => $cr->id, 'status' => 'accepted']);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 19. Requester cannot accept their own sent request (403)
    // ══════════════════════════════════════════════════════════════════════════

    public function test_requester_cannot_accept_own_sent_request(): void
    {
        $a = $this->discoverableUser();
        $b = $this->discoverableUser();
        $cr = ConnectionRequest::factory()->create(['requester_id' => $a->id, 'recipient_id' => $b->id]);

        $this->actingAs($a, 'sanctum')
            ->postJson($this->acceptUrl($cr))
            ->assertStatus(403);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 20. Third-party user cannot accept a request (403)
    // ══════════════════════════════════════════════════════════════════════════

    public function test_third_party_cannot_accept(): void
    {
        $a       = $this->discoverableUser();
        $b       = $this->discoverableUser();
        $stranger = $this->discoverableUser();
        $cr = ConnectionRequest::factory()->create(['requester_id' => $a->id, 'recipient_id' => $b->id]);

        $this->actingAs($stranger, 'sanctum')
            ->postJson($this->acceptUrl($cr))
            ->assertStatus(403);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 21. Recipient can reject a pending request
    // ══════════════════════════════════════════════════════════════════════════

    public function test_recipient_can_reject_pending_request(): void
    {
        $a = $this->discoverableUser();
        $b = $this->discoverableUser();
        $cr = ConnectionRequest::factory()->create(['requester_id' => $a->id, 'recipient_id' => $b->id]);

        $this->actingAs($b, 'sanctum')
            ->postJson($this->rejectUrl($cr))
            ->assertStatus(200)
            ->assertJsonPath('data.connection_request.status', 'rejected');

        $this->assertDatabaseHas('connection_requests', ['id' => $cr->id, 'status' => 'rejected']);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 22. Requester cannot reject their own sent request (403)
    // ══════════════════════════════════════════════════════════════════════════

    public function test_requester_cannot_reject_own_sent_request(): void
    {
        $a = $this->discoverableUser();
        $b = $this->discoverableUser();
        $cr = ConnectionRequest::factory()->create(['requester_id' => $a->id, 'recipient_id' => $b->id]);

        $this->actingAs($a, 'sanctum')
            ->postJson($this->rejectUrl($cr))
            ->assertStatus(403);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 23. Requester can cancel their own sent pending request
    // ══════════════════════════════════════════════════════════════════════════

    public function test_requester_can_cancel_own_pending_request(): void
    {
        $a = $this->discoverableUser();
        $b = $this->discoverableUser();
        $cr = ConnectionRequest::factory()->create(['requester_id' => $a->id, 'recipient_id' => $b->id]);

        $this->actingAs($a, 'sanctum')
            ->postJson($this->cancelUrl($cr))
            ->assertStatus(200)
            ->assertJsonPath('data.connection_request.status', 'cancelled');

        $this->assertDatabaseHas('connection_requests', ['id' => $cr->id, 'status' => 'cancelled']);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 24. Recipient cannot cancel someone else's sent request (403)
    // ══════════════════════════════════════════════════════════════════════════

    public function test_recipient_cannot_cancel_received_request(): void
    {
        $a = $this->discoverableUser();
        $b = $this->discoverableUser();
        $cr = ConnectionRequest::factory()->create(['requester_id' => $a->id, 'recipient_id' => $b->id]);

        $this->actingAs($b, 'sanctum')
            ->postJson($this->cancelUrl($cr))
            ->assertStatus(403);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 25. Non-pending accept returns 409
    // ══════════════════════════════════════════════════════════════════════════

    public function test_non_pending_accept_returns_409(): void
    {
        $a = $this->discoverableUser();
        $b = $this->discoverableUser();
        $cr = ConnectionRequest::factory()->rejected()->create(['requester_id' => $a->id, 'recipient_id' => $b->id]);

        $this->actingAs($b, 'sanctum')
            ->postJson($this->acceptUrl($cr))
            ->assertStatus(409);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 26. Non-pending reject returns 409
    // ══════════════════════════════════════════════════════════════════════════

    public function test_non_pending_reject_returns_409(): void
    {
        $a = $this->discoverableUser();
        $b = $this->discoverableUser();
        $cr = ConnectionRequest::factory()->accepted()->create(['requester_id' => $a->id, 'recipient_id' => $b->id]);

        $this->actingAs($b, 'sanctum')
            ->postJson($this->rejectUrl($cr))
            ->assertStatus(409);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 27. Non-pending cancel returns 409
    // ══════════════════════════════════════════════════════════════════════════

    public function test_non_pending_cancel_returns_409(): void
    {
        $a = $this->discoverableUser();
        $b = $this->discoverableUser();
        $cr = ConnectionRequest::factory()->accepted()->create(['requester_id' => $a->id, 'recipient_id' => $b->id]);

        $this->actingAs($a, 'sanctum')
            ->postJson($this->cancelUrl($cr))
            ->assertStatus(409);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 28. Resource does NOT expose email or private fields
    // ══════════════════════════════════════════════════════════════════════════

    public function test_response_does_not_expose_email_or_private_fields(): void
    {
        $a = $this->discoverableUser();
        $b = $this->discoverableUser();

        $response = $this->actingAs($a, 'sanctum')
            ->postJson($this->sendUrl(), ['recipient_id' => $b->id]);

        $response->assertStatus(201);

        $json = $response->json();

        // Check requester and recipient nodes do not contain email
        $requesterData = $json['data']['connection_request']['requester'];
        $recipientData = $json['data']['connection_request']['recipient'];

        $this->assertArrayNotHasKey('email', $requesterData);
        $this->assertArrayNotHasKey('email_verified_at', $requesterData);
        $this->assertArrayNotHasKey('password', $requesterData);
        $this->assertArrayNotHasKey('email', $recipientData);
        $this->assertArrayNotHasKey('email_verified_at', $recipientData);
        $this->assertArrayNotHasKey('preferred_budget_min', $recipientData);
        $this->assertArrayNotHasKey('preferred_budget_max', $recipientData);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 29. API response structure is correct
    // ══════════════════════════════════════════════════════════════════════════

    public function test_api_response_structure_is_correct(): void
    {
        $a = $this->discoverableUser();
        $b = $this->discoverableUser();

        $response = $this->actingAs($a, 'sanctum')
            ->postJson($this->sendUrl(), ['recipient_id' => $b->id]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'connection_request' => [
                        'id',
                        'status',
                        'requester' => ['id', 'name', 'profile_photo_url'],
                        'recipient' => ['id', 'name', 'profile_photo_url'],
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);
    }
}
