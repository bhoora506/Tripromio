<?php

namespace Tests\Feature\Connection;

use App\Enums\ConnectionStatus;
use App\Models\ConnectionRequest;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * G2 API Tests — My Connections Endpoint.
 */
class MyConnectionsApiTest extends TestCase
{
    use RefreshDatabase;

    private function connectionsUrl(): string
    {
        return '/api/connections';
    }

    private function discoverableUser(): User
    {
        $user = User::factory()->create();
        UserProfile::create([
            'user_id'         => $user->id,
            'is_discoverable' => true,
        ]);
        return $user;
    }

    public function test_authenticated_requester_sees_accepted_connection(): void
    {
        $requester = $this->discoverableUser();
        $recipient = $this->discoverableUser();

        ConnectionRequest::factory()->accepted()->create([
            'requester_id' => $requester->id,
            'recipient_id' => $recipient->id,
        ]);

        $this->actingAs($requester, 'sanctum')
            ->getJson($this->connectionsUrl())
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $recipient->id);
    }

    public function test_authenticated_recipient_sees_accepted_connection(): void
    {
        $requester = $this->discoverableUser();
        $recipient = $this->discoverableUser();

        ConnectionRequest::factory()->accepted()->create([
            'requester_id' => $requester->id,
            'recipient_id' => $recipient->id,
        ]);

        $this->actingAs($recipient, 'sanctum')
            ->getJson($this->connectionsUrl())
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $requester->id);
    }

    public function test_pending_request_is_not_returned(): void
    {
        $requester = $this->discoverableUser();
        $recipient = $this->discoverableUser();

        ConnectionRequest::factory()->create([
            'requester_id' => $requester->id,
            'recipient_id' => $recipient->id,
        ]);

        $this->actingAs($requester, 'sanctum')
            ->getJson($this->connectionsUrl())
            ->assertStatus(200)
            ->assertJsonCount(0, 'data.items');
    }

    public function test_rejected_and_cancelled_requests_are_not_returned(): void
    {
        $user = $this->discoverableUser();
        $other1 = $this->discoverableUser();
        $other2 = $this->discoverableUser();

        ConnectionRequest::factory()->rejected()->create([
            'requester_id' => $user->id,
            'recipient_id' => $other1->id,
        ]);

        ConnectionRequest::factory()->cancelled()->create([
            'requester_id' => $other2->id,
            'recipient_id' => $user->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson($this->connectionsUrl())
            ->assertStatus(200)
            ->assertJsonCount(0, 'data.items');
    }

    public function test_multiple_accepted_connections_are_returned(): void
    {
        $user = $this->discoverableUser();
        $friend1 = $this->discoverableUser();
        $friend2 = $this->discoverableUser();

        // User sent request to friend1
        ConnectionRequest::factory()->accepted()->create([
            'requester_id' => $user->id,
            'recipient_id' => $friend1->id,
        ]);

        // friend2 sent request to user
        ConnectionRequest::factory()->accepted()->create([
            'requester_id' => $friend2->id,
            'recipient_id' => $user->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson($this->connectionsUrl())
            ->assertStatus(200)
            ->assertJsonCount(2, 'data.items');
    }

    public function test_pagination_works_and_default_is_20(): void
    {
        $user = $this->discoverableUser();

        // Create 25 connections
        for ($i = 0; $i < 25; $i++) {
            $friend = $this->discoverableUser();
            ConnectionRequest::factory()->accepted()->create([
                'requester_id' => $user->id,
                'recipient_id' => $friend->id,
            ]);
        }

        // Test default pagination (20)
        $this->actingAs($user, 'sanctum')
            ->getJson($this->connectionsUrl())
            ->assertStatus(200)
            ->assertJsonCount(20, 'data.items')
            ->assertJsonPath('data.pagination.total', 25)
            ->assertJsonPath('data.pagination.per_page', 20)
            ->assertJsonPath('data.pagination.has_more', true);

        // Test per_page = 1
        $this->actingAs($user, 'sanctum')
            ->getJson($this->connectionsUrl() . '?per_page=1')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.pagination.per_page', 1)
            ->assertJsonPath('data.pagination.has_more', true);
            
        // Test per_page max limit (50) via validation rules
        $this->actingAs($user, 'sanctum')
            ->getJson($this->connectionsUrl() . '?per_page=100')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_privacy_response_does_not_contain_sensitive_fields(): void
    {
        $user = $this->discoverableUser();
        $friend = $this->discoverableUser();

        ConnectionRequest::factory()->accepted()->create([
            'requester_id' => $user->id,
            'recipient_id' => $friend->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson($this->connectionsUrl());

        $response->assertStatus(200);
        $json = $response->json();

        $itemData = $json['data']['items'][0];

        $this->assertArrayNotHasKey('email', $itemData);
        $this->assertArrayNotHasKey('password', $itemData);
        $this->assertArrayNotHasKey('remember_token', $itemData);
        $this->assertArrayNotHasKey('email_verified_at', $itemData);
        $this->assertArrayNotHasKey('tokens', $itemData);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson($this->connectionsUrl())
            ->assertStatus(401);
    }

    public function test_user_cannot_see_unrelated_connection(): void
    {
        $userA = $this->discoverableUser();
        $userB = $this->discoverableUser();
        $userC = $this->discoverableUser();

        // A and B are connected
        ConnectionRequest::factory()->accepted()->create([
            'requester_id' => $userA->id,
            'recipient_id' => $userB->id,
        ]);

        // C calls GET /api/connections
        $this->actingAs($userC, 'sanctum')
            ->getJson($this->connectionsUrl())
            ->assertStatus(200)
            ->assertJsonCount(0, 'data.items');
    }
}
