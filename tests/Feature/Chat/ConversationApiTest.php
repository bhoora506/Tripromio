<?php

namespace Tests\Feature\Chat;

use App\Models\Conversation;
use App\Models\ConnectionRequest;
use App\Models\Message;
use App\Models\Trip;
use App\Models\TripMember;
use App\Models\TripJoinRequest;
use App\Enums\MemberStatus;
use App\Enums\JoinRequestStatus;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * G3 API Tests — Chat / Messaging Endpoints.
 *
 * Covers all required scenarios:
 *   - Authentication gates
 *   - Connection rule (accepted-only)
 *   - Duplicate conversation handling
 *   - Participation authorization
 *   - Message creation, pagination, ordering
 *   - Read/unread state
 *   - Privacy (no email/password/tokens exposed)
 */
class ConversationApiTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeUser(): User
    {
        $user = User::factory()->create();
        UserProfile::create(['user_id' => $user->id, 'is_discoverable' => true]);
        return $user;
    }

    private function acceptedConnection(User $a, User $b): ConnectionRequest
    {
        return ConnectionRequest::factory()->accepted()->create([
            'requester_id' => $a->id,
            'recipient_id' => $b->id,
        ]);
    }

    private function conversation(User $a, User $b): Conversation
    {
        [$reqId, $recId] = [$a->id < $b->id ? $a->id : $b->id, $a->id < $b->id ? $b->id : $a->id];
        return Conversation::create(['requester_id' => $reqId, 'recipient_id' => $recId]);
    }

    private function conversationsUrl(): string         { return '/api/conversations'; }
    private function storeUrl(): string                 { return '/api/conversations'; }
    private function messagesUrl(Conversation $c): string { return "/api/conversations/{$c->id}/messages"; }
    private function sendUrl(Conversation $c): string     { return "/api/conversations/{$c->id}/messages"; }
    private function readUrl(Conversation $c): string     { return "/api/conversations/{$c->id}/read"; }

    // ══════════════════════════════════════════════════════════════════════════
    // 1–3. AUTHENTICATION
    // ══════════════════════════════════════════════════════════════════════════

    public function test_unauthenticated_cannot_list_conversations(): void
    {
        $this->getJson($this->conversationsUrl())->assertStatus(401);
    }

    public function test_unauthenticated_cannot_create_conversation(): void
    {
        $this->postJson($this->storeUrl(), ['recipient_id' => 1])->assertStatus(401);
    }

    public function test_unauthenticated_cannot_access_messages(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();
        $this->acceptedConnection($a, $b);
        $conv = $this->conversation($a, $b);

        $this->getJson($this->messagesUrl($conv))->assertStatus(401);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 4–9. CONNECTION RULE
    // ══════════════════════════════════════════════════════════════════════════

    public function test_accepted_connection_can_create_conversation(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();
        $this->acceptedConnection($a, $b);

        $this->actingAs($a, 'sanctum')
            ->postJson($this->storeUrl(), ['recipient_id' => $b->id])
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.conversation.other_participant.id', $b->id);
    }

    public function test_pending_connection_cannot_create_conversation(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();
        ConnectionRequest::factory()->create(['requester_id' => $a->id, 'recipient_id' => $b->id]);

        $this->actingAs($a, 'sanctum')
            ->postJson($this->storeUrl(), ['recipient_id' => $b->id])
            ->assertStatus(409);
    }

    public function test_rejected_connection_cannot_create_conversation(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();
        ConnectionRequest::factory()->rejected()->create(['requester_id' => $a->id, 'recipient_id' => $b->id]);

        $this->actingAs($a, 'sanctum')
            ->postJson($this->storeUrl(), ['recipient_id' => $b->id])
            ->assertStatus(409);
    }

    public function test_cancelled_connection_cannot_create_conversation(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();
        ConnectionRequest::factory()->cancelled()->create(['requester_id' => $a->id, 'recipient_id' => $b->id]);

        $this->actingAs($a, 'sanctum')
            ->postJson($this->storeUrl(), ['recipient_id' => $b->id])
            ->assertStatus(409);
    }

    public function test_no_connection_cannot_create_conversation(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();

        $this->actingAs($a, 'sanctum')
            ->postJson($this->storeUrl(), ['recipient_id' => $b->id])
            ->assertStatus(409);
    }

    public function test_self_conversation_is_rejected(): void
    {
        $a = $this->makeUser();

        $this->actingAs($a, 'sanctum')
            ->postJson($this->storeUrl(), ['recipient_id' => $a->id])
            ->assertStatus(409);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 10–11. DUPLICATE CONVERSATION
    // ══════════════════════════════════════════════════════════════════════════

    public function test_repeated_create_returns_same_conversation(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();
        $this->acceptedConnection($a, $b);

        $first = $this->actingAs($a, 'sanctum')
            ->postJson($this->storeUrl(), ['recipient_id' => $b->id])
            ->assertStatus(201)
            ->json('data.conversation.id');

        $second = $this->actingAs($a, 'sanctum')
            ->postJson($this->storeUrl(), ['recipient_id' => $b->id])
            ->assertStatus(200)  // already exists
            ->json('data.conversation.id');

        $this->assertSame($first, $second);
        $this->assertDatabaseCount('conversations', 1);
    }

    public function test_reverse_direction_create_returns_same_conversation(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();
        $this->acceptedConnection($a, $b);

        // A initiates
        $firstId = $this->actingAs($a, 'sanctum')
            ->postJson($this->storeUrl(), ['recipient_id' => $b->id])
            ->assertStatus(201)
            ->json('data.conversation.id');

        // B initiates (reverse)
        $secondId = $this->actingAs($b, 'sanctum')
            ->postJson($this->storeUrl(), ['recipient_id' => $a->id])
            ->assertStatus(200)
            ->json('data.conversation.id');

        $this->assertSame($firstId, $secondId);
        $this->assertDatabaseCount('conversations', 1);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 12–19. PARTICIPATION AUTHORIZATION
    // ══════════════════════════════════════════════════════════════════════════

    public function test_participant_can_view_their_conversation_list(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();
        $this->acceptedConnection($a, $b);
        $this->conversation($a, $b);

        $this->actingAs($a, 'sanctum')
            ->getJson($this->conversationsUrl())
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.conversations');
    }

    public function test_participant_can_retrieve_messages(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();
        $this->acceptedConnection($a, $b);
        $conv = $this->conversation($a, $b);

        $this->actingAs($a, 'sanctum')
            ->getJson($this->messagesUrl($conv))
            ->assertStatus(200);
    }

    public function test_participant_can_send_message(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();
        $this->acceptedConnection($a, $b);
        $conv = $this->conversation($a, $b);

        $this->actingAs($a, 'sanctum')
            ->postJson($this->sendUrl($conv), ['body' => 'Hello!'])
            ->assertStatus(201)
            ->assertJsonPath('data.message.body', 'Hello!');
    }

    public function test_participant_can_mark_read(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();
        $this->acceptedConnection($a, $b);
        $conv = $this->conversation($a, $b);

        $this->actingAs($a, 'sanctum')
            ->postJson($this->readUrl($conv))
            ->assertStatus(200);
    }

    public function test_non_participant_cannot_view_conversation_messages(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();
        $stranger = $this->makeUser();
        $this->acceptedConnection($a, $b);
        $conv = $this->conversation($a, $b);

        $this->actingAs($stranger, 'sanctum')
            ->getJson($this->messagesUrl($conv))
            ->assertStatus(403);
    }

    public function test_non_participant_cannot_send_message(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();
        $stranger = $this->makeUser();
        $this->acceptedConnection($a, $b);
        $conv = $this->conversation($a, $b);

        $this->actingAs($stranger, 'sanctum')
            ->postJson($this->sendUrl($conv), ['body' => 'Hi!'])
            ->assertStatus(403);
    }

    public function test_non_participant_cannot_mark_read(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();
        $stranger = $this->makeUser();
        $this->acceptedConnection($a, $b);
        $conv = $this->conversation($a, $b);

        $this->actingAs($stranger, 'sanctum')
            ->postJson($this->readUrl($conv))
            ->assertStatus(403);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 20–25. MESSAGES
    // ══════════════════════════════════════════════════════════════════════════

    public function test_message_is_stored_correctly(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();
        $this->acceptedConnection($a, $b);
        $conv = $this->conversation($a, $b);

        $this->actingAs($a, 'sanctum')
            ->postJson($this->sendUrl($conv), ['body' => 'Test message'])
            ->assertStatus(201);

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conv->id,
            'sender_id'       => $a->id,
            'body'            => 'Test message',
        ]);
    }

    public function test_empty_body_is_rejected(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();
        $this->acceptedConnection($a, $b);
        $conv = $this->conversation($a, $b);

        $this->actingAs($a, 'sanctum')
            ->postJson($this->sendUrl($conv), ['body' => ''])
            ->assertStatus(422);
    }

    public function test_whitespace_only_body_is_rejected(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();
        $this->acceptedConnection($a, $b);
        $conv = $this->conversation($a, $b);

        // The service trims before checking; the controller validates non-empty.
        // A body of only whitespace will pass Laravel validation (non-empty string)
        // but be caught by the service trim check.
        $this->actingAs($a, 'sanctum')
            ->postJson($this->sendUrl($conv), ['body' => '   '])
            ->assertStatus(422);
    }

    public function test_body_exceeding_max_length_is_rejected(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();
        $this->acceptedConnection($a, $b);
        $conv = $this->conversation($a, $b);

        $this->actingAs($a, 'sanctum')
            ->postJson($this->sendUrl($conv), ['body' => str_repeat('x', 5001)])
            ->assertStatus(422);
    }

    public function test_messages_are_paginated(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();
        $this->acceptedConnection($a, $b);
        $conv = $this->conversation($a, $b);

        // Create 25 messages
        for ($i = 0; $i < 25; $i++) {
            Message::create(['conversation_id' => $conv->id, 'sender_id' => $a->id, 'body' => "Msg $i"]);
        }

        $this->actingAs($a, 'sanctum')
            ->getJson($this->messagesUrl($conv) . '?per_page=10')
            ->assertStatus(200)
            ->assertJsonCount(10, 'data.items')
            ->assertJsonPath('data.pagination.total', 25)
            ->assertJsonPath('data.pagination.has_more', true);
    }

    public function test_messages_are_newest_first(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();
        $this->acceptedConnection($a, $b);
        $conv = $this->conversation($a, $b);

        $first = Message::create(['conversation_id' => $conv->id, 'sender_id' => $a->id, 'body' => 'First', 'created_at' => now()->subMinutes(10), 'updated_at' => now()->subMinutes(10)]);
        $last  = Message::create(['conversation_id' => $conv->id, 'sender_id' => $a->id, 'body' => 'Last',  'created_at' => now(), 'updated_at' => now()]);

        $items = $this->actingAs($a, 'sanctum')
            ->getJson($this->messagesUrl($conv))
            ->assertStatus(200)
            ->json('data.items');

        // Newest-first: 'Last' message should be first in the response
        $this->assertSame('Last',  $items[0]['body']);
        $this->assertSame('First', $items[1]['body']);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 26–29. READ STATE
    // ══════════════════════════════════════════════════════════════════════════

    public function test_unread_count_is_correct(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();
        $this->acceptedConnection($a, $b);
        $conv = $this->conversation($a, $b);

        // B sends 3 messages to A
        Message::create(['conversation_id' => $conv->id, 'sender_id' => $b->id, 'body' => 'Hi 1']);
        Message::create(['conversation_id' => $conv->id, 'sender_id' => $b->id, 'body' => 'Hi 2']);
        Message::create(['conversation_id' => $conv->id, 'sender_id' => $b->id, 'body' => 'Hi 3']);

        // A's conversation list should show unread_count = 3
        $conversations = $this->actingAs($a, 'sanctum')
            ->getJson($this->conversationsUrl())
            ->assertStatus(200)
            ->json('data.conversations');

        $this->assertSame(3, $conversations[0]['unread_count']);
    }

    public function test_mark_read_updates_other_users_messages(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();
        $this->acceptedConnection($a, $b);
        $conv = $this->conversation($a, $b);

        // B sends 2 messages
        $m1 = Message::create(['conversation_id' => $conv->id, 'sender_id' => $b->id, 'body' => 'Hey']);
        $m2 = Message::create(['conversation_id' => $conv->id, 'sender_id' => $b->id, 'body' => 'You there?']);

        // A marks as read
        $this->actingAs($a, 'sanctum')
            ->postJson($this->readUrl($conv))
            ->assertStatus(200);

        // Both messages from B should now be read
        $this->assertNotNull(Message::find($m1->id)->read_at);
        $this->assertNotNull(Message::find($m2->id)->read_at);
    }

    public function test_own_messages_are_not_marked_as_read(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();
        $this->acceptedConnection($a, $b);
        $conv = $this->conversation($a, $b);

        // A sends a message
        $ownMsg = Message::create(['conversation_id' => $conv->id, 'sender_id' => $a->id, 'body' => 'My own msg']);

        // A calls markAsRead — own message should NOT be touched
        $this->actingAs($a, 'sanctum')
            ->postJson($this->readUrl($conv))
            ->assertStatus(200);

        $this->assertNull(Message::find($ownMsg->id)->read_at);
    }

    public function test_mark_read_is_idempotent(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();
        $this->acceptedConnection($a, $b);
        $conv = $this->conversation($a, $b);

        Message::create(['conversation_id' => $conv->id, 'sender_id' => $b->id, 'body' => 'Hey']);

        // Call twice — should succeed both times
        $this->actingAs($a, 'sanctum')->postJson($this->readUrl($conv))->assertStatus(200);
        $this->actingAs($a, 'sanctum')->postJson($this->readUrl($conv))->assertStatus(200);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 30–34. CONVERSATION LIST
    // ══════════════════════════════════════════════════════════════════════════

    public function test_user_sees_only_own_conversations(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();
        $c = $this->makeUser();

        $this->acceptedConnection($a, $b);
        $this->conversation($a, $b);

        $this->acceptedConnection($b, $c);
        $this->conversation($b, $c);

        // A only sees the A-B conversation, not B-C
        $this->actingAs($a, 'sanctum')
            ->getJson($this->conversationsUrl())
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.conversations');
    }

    public function test_conversation_list_includes_latest_message(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();
        $this->acceptedConnection($a, $b);
        $conv = $this->conversation($a, $b);

        Message::create(['conversation_id' => $conv->id, 'sender_id' => $a->id, 'body' => 'Newest']);

        $list = $this->actingAs($a, 'sanctum')
            ->getJson($this->conversationsUrl())
            ->assertStatus(200)
            ->json('data.conversations');

        $this->assertSame('Newest', $list[0]['latest_message']['body']);
    }

    public function test_conversation_list_returns_other_participant(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();
        $this->acceptedConnection($a, $b);
        $this->conversation($a, $b);

        $list = $this->actingAs($a, 'sanctum')
            ->getJson($this->conversationsUrl())
            ->assertStatus(200)
            ->json('data.conversations');

        $this->assertSame($b->id, $list[0]['other_participant']['id']);
    }

    public function test_conversation_list_unread_count_is_correct(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();
        $this->acceptedConnection($a, $b);
        $conv = $this->conversation($a, $b);

        Message::create(['conversation_id' => $conv->id, 'sender_id' => $b->id, 'body' => 'Unread!']);

        $list = $this->actingAs($a, 'sanctum')
            ->getJson($this->conversationsUrl())
            ->assertStatus(200)
            ->json('data.conversations');

        $this->assertSame(1, $list[0]['unread_count']);
    }

    public function test_conversations_ordered_by_latest_activity(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();
        $c = $this->makeUser();

        $this->acceptedConnection($a, $b);
        $this->acceptedConnection($a, $c);

        $oldConv  = $this->conversation($a, $b);
        $newConv  = $this->conversation($a, $c);

        // Touch newConv more recently
        $oldConv->updated_at = now()->subHour();
        $oldConv->save();

        $newConv->updated_at = now();
        $newConv->save();

        $list = $this->actingAs($a, 'sanctum')
            ->getJson($this->conversationsUrl())
            ->assertStatus(200)
            ->json('data.conversations');

        $this->assertSame($newConv->id, $list[0]['id']);
        $this->assertSame($oldConv->id, $list[1]['id']);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 35–36. PRIVACY
    // ══════════════════════════════════════════════════════════════════════════

    public function test_conversation_response_does_not_expose_sensitive_fields(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();
        $this->acceptedConnection($a, $b);
        $this->conversation($a, $b);

        $list = $this->actingAs($a, 'sanctum')
            ->getJson($this->conversationsUrl())
            ->assertStatus(200)
            ->json('data.conversations');

        $other = $list[0]['other_participant'];
        $this->assertArrayNotHasKey('email', $other);
        $this->assertArrayNotHasKey('password', $other);
        $this->assertArrayNotHasKey('remember_token', $other);
        $this->assertArrayNotHasKey('email_verified_at', $other);
    }

    public function test_message_response_does_not_expose_sensitive_fields(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();
        $this->acceptedConnection($a, $b);
        $conv = $this->conversation($a, $b);

        Message::create(['conversation_id' => $conv->id, 'sender_id' => $a->id, 'body' => 'Hi']);

        $items = $this->actingAs($a, 'sanctum')
            ->getJson($this->messagesUrl($conv))
            ->assertStatus(200)
            ->json('data.items');

        $sender = $items[0]['sender'];
        $this->assertArrayNotHasKey('email', $sender);
        $this->assertArrayNotHasKey('password', $sender);
        $this->assertArrayNotHasKey('remember_token', $sender);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // TRIP-BASED CHAT PERMISSION
    // ══════════════════════════════════════════════════════════════════════════

    private function makeActiveMember(Trip $trip, User $user, string $role = 'member'): TripMember
    {
        return TripMember::create([
            'trip_id'   => $trip->id,
            'user_id'   => $user->id,
            'role'      => $role,
            'status'    => MemberStatus::Active->value,
            'joined_at' => now(),
        ]);
    }

    public function test_same_active_trip_without_connection_can_create_conversation(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();
        $trip = Trip::factory()->create(['user_id' => $a->id]);

        $this->makeActiveMember($trip, $a, 'owner');
        $this->makeActiveMember($trip, $b, 'member');

        $this->actingAs($a, 'sanctum')
            ->postJson($this->storeUrl(), ['recipient_id' => $b->id])
            ->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_same_active_trip_without_connection_can_send_message(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();
        $trip = Trip::factory()->create(['user_id' => $a->id]);

        $this->makeActiveMember($trip, $a, 'owner');
        $this->makeActiveMember($trip, $b, 'member');
        
        $conv = $this->conversation($a, $b);

        $this->actingAs($b, 'sanctum')
            ->postJson($this->sendUrl($conv), ['body' => 'Hello from trip'])
            ->assertStatus(201);
    }

    public function test_different_trips_without_connection_cannot_create_conversation(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();
        $trip1 = Trip::factory()->create(['user_id' => $a->id]);
        $trip2 = Trip::factory()->create(['user_id' => $b->id]);

        $this->makeActiveMember($trip1, $a, 'owner');
        $this->makeActiveMember($trip2, $b, 'owner');

        $this->actingAs($a, 'sanctum')
            ->postJson($this->storeUrl(), ['recipient_id' => $b->id])
            ->assertStatus(409);
    }

    public function test_pending_join_request_without_connection_cannot_create_conversation(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();
        $trip = Trip::factory()->create(['user_id' => $a->id]);

        $this->makeActiveMember($trip, $a, 'owner');
        TripJoinRequest::create([
            'trip_id' => $trip->id,
            'user_id' => $b->id,
            'status'  => JoinRequestStatus::Pending->value,
        ]);

        $this->actingAs($b, 'sanctum')
            ->postJson($this->storeUrl(), ['recipient_id' => $a->id])
            ->assertStatus(409);
    }

    public function test_rejected_join_request_without_connection_cannot_create_conversation(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();
        $trip = Trip::factory()->create(['user_id' => $a->id]);

        $this->makeActiveMember($trip, $a, 'owner');
        TripJoinRequest::create([
            'trip_id' => $trip->id,
            'user_id' => $b->id,
            'status'  => JoinRequestStatus::Rejected->value,
        ]);

        $this->actingAs($b, 'sanctum')
            ->postJson($this->storeUrl(), ['recipient_id' => $a->id])
            ->assertStatus(409);
    }

    public function test_left_trip_membership_without_connection_cannot_create_conversation(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();
        $trip = Trip::factory()->create(['user_id' => $a->id]);

        $this->makeActiveMember($trip, $a, 'owner');
        TripMember::create([
            'trip_id'   => $trip->id,
            'user_id'   => $b->id,
            'role'      => 'member',
            'status'    => MemberStatus::Left->value,
            'joined_at' => now(),
        ]);

        $this->actingAs($b, 'sanctum')
            ->postJson($this->storeUrl(), ['recipient_id' => $a->id])
            ->assertStatus(409);
    }

    public function test_removed_trip_membership_without_connection_cannot_create_conversation(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();
        $trip = Trip::factory()->create(['user_id' => $a->id]);

        $this->makeActiveMember($trip, $a, 'owner');
        TripMember::create([
            'trip_id'   => $trip->id,
            'user_id'   => $b->id,
            'role'      => 'member',
            'status'    => MemberStatus::Removed->value,
            'joined_at' => now(),
        ]);

        $this->actingAs($b, 'sanctum')
            ->postJson($this->storeUrl(), ['recipient_id' => $a->id])
            ->assertStatus(409);
    }

    public function test_accepted_connection_without_common_trip_can_still_chat(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();
        $this->acceptedConnection($a, $b);

        $this->actingAs($a, 'sanctum')
            ->postJson($this->storeUrl(), ['recipient_id' => $b->id])
            ->assertStatus(201);
    }

    public function test_accepting_join_request_does_not_create_conversation(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();
        $trip = Trip::factory()->create(['user_id' => $a->id]);

        $this->makeActiveMember($trip, $a, 'owner');
        $this->makeActiveMember($trip, $b, 'member');

        $this->assertDatabaseMissing('conversations', [
            'requester_id' => min($a->id, $b->id),
            'recipient_id' => max($a->id, $b->id),
        ]);
    }

    public function test_trip_owner_and_member_can_chat_via_trip_membership(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();
        $c = $this->makeUser();
        $trip = Trip::factory()->create(['user_id' => $a->id]);

        $this->makeActiveMember($trip, $a, 'owner');
        $this->makeActiveMember($trip, $b, 'member');
        $this->makeActiveMember($trip, $c, 'member');

        // B and C can chat (both members)
        $this->actingAs($b, 'sanctum')
            ->postJson($this->storeUrl(), ['recipient_id' => $c->id])
            ->assertStatus(201);
    }

    public function test_self_chat_still_rejected_with_common_trip(): void
    {
        $a = $this->makeUser();
        $trip = Trip::factory()->create(['user_id' => $a->id]);

        $this->makeActiveMember($trip, $a, 'owner');

        $this->actingAs($a, 'sanctum')
            ->postJson($this->storeUrl(), ['recipient_id' => $a->id])
            ->assertStatus(409);
    }
}
