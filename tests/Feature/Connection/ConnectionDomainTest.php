<?php

namespace Tests\Feature\Connection;

use App\Enums\ConnectionStatus;
use App\Models\ConnectionRequest;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F1 Domain Tests — Connection Request & Companion Discovery Foundation.
 *
 * Coverage:
 *   - is_discoverable field behaviour
 *   - ConnectionStatus enum
 *   - ConnectionRequest model, factory, and relationships
 *   - User relationship methods
 *   - Schema / index presence where practical
 *
 * Does NOT test any API endpoint (those belong to F2/F4).
 */
class ConnectionDomainTest extends TestCase
{
    use RefreshDatabase;

    // ══════════════════════════════════════════════════════════════════════════
    // is_discoverable
    // ══════════════════════════════════════════════════════════════════════════

    public function test_is_discoverable_defaults_to_true_when_profile_created(): void
    {
        $user    = User::factory()->create();
        $profile = UserProfile::create(['user_id' => $user->id]);

        $this->assertTrue($profile->fresh()->is_discoverable);
    }

    public function test_is_discoverable_is_cast_to_boolean(): void
    {
        $user    = User::factory()->create();
        $profile = UserProfile::create(['user_id' => $user->id]);

        $this->assertIsBool($profile->fresh()->is_discoverable);
    }

    public function test_is_discoverable_can_be_set_to_false(): void
    {
        $user    = User::factory()->create();
        $profile = UserProfile::create([
            'user_id'         => $user->id,
            'is_discoverable' => false,
        ]);

        $this->assertFalse($profile->fresh()->is_discoverable);
    }

    public function test_existing_profile_row_remains_discoverable_after_migration(): void
    {
        // Simulate an "existing" profile that was created without explicitly
        // setting is_discoverable — the DB default true must apply.
        $user = User::factory()->create();
        UserProfile::create(['user_id' => $user->id]);

        $profile = UserProfile::where('user_id', $user->id)->first();

        $this->assertTrue($profile->is_discoverable);
    }

    public function test_is_discoverable_in_fillable(): void
    {
        $user    = User::factory()->create();
        $profile = UserProfile::create([
            'user_id'         => $user->id,
            'is_discoverable' => false,
        ]);
        $profile->update(['is_discoverable' => true]);

        $this->assertTrue($profile->fresh()->is_discoverable);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // ConnectionStatus Enum
    // ══════════════════════════════════════════════════════════════════════════

    public function test_connection_status_enum_has_correct_values(): void
    {
        $this->assertSame(['pending', 'accepted', 'rejected', 'cancelled'], ConnectionStatus::values());
    }

    public function test_connection_status_terminal_states(): void
    {
        $this->assertTrue(ConnectionStatus::Accepted->isTerminal());
        $this->assertTrue(ConnectionStatus::Rejected->isTerminal());
        $this->assertTrue(ConnectionStatus::Cancelled->isTerminal());
        $this->assertFalse(ConnectionStatus::Pending->isTerminal());
    }

    // ══════════════════════════════════════════════════════════════════════════
    // ConnectionRequest Factory
    // ══════════════════════════════════════════════════════════════════════════

    public function test_connection_request_factory_creates_valid_pending_request(): void
    {
        $cr = ConnectionRequest::factory()->create();

        $this->assertNotNull($cr->id);
        $this->assertNotNull($cr->requester_id);
        $this->assertNotNull($cr->recipient_id);
        $this->assertInstanceOf(ConnectionStatus::class, $cr->status);
        $this->assertSame(ConnectionStatus::Pending, $cr->status);
    }

    public function test_connection_request_factory_accepted_state(): void
    {
        $cr = ConnectionRequest::factory()->accepted()->create();
        $this->assertSame(ConnectionStatus::Accepted, $cr->fresh()->status);
    }

    public function test_connection_request_factory_rejected_state(): void
    {
        $cr = ConnectionRequest::factory()->rejected()->create();
        $this->assertSame(ConnectionStatus::Rejected, $cr->fresh()->status);
    }

    public function test_connection_request_factory_cancelled_state(): void
    {
        $cr = ConnectionRequest::factory()->cancelled()->create();
        $this->assertSame(ConnectionStatus::Cancelled, $cr->fresh()->status);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // ConnectionRequest Model Casts & Helpers
    // ══════════════════════════════════════════════════════════════════════════

    public function test_status_is_cast_to_connection_status_enum(): void
    {
        $cr = ConnectionRequest::factory()->create();
        $this->assertInstanceOf(ConnectionStatus::class, ConnectionRequest::find($cr->id)->status);
    }

    public function test_is_pending_helper(): void
    {
        $pending   = ConnectionRequest::factory()->create();
        $accepted  = ConnectionRequest::factory()->accepted()->create();

        $this->assertTrue($pending->isPending());
        $this->assertFalse($accepted->isPending());
    }

    public function test_is_accepted_helper(): void
    {
        $accepted = ConnectionRequest::factory()->accepted()->create();
        $pending  = ConnectionRequest::factory()->create();

        $this->assertTrue($accepted->isAccepted());
        $this->assertFalse($pending->isAccepted());
    }

    public function test_is_rejected_helper(): void
    {
        $rejected = ConnectionRequest::factory()->rejected()->create();
        $pending  = ConnectionRequest::factory()->create();

        $this->assertTrue($rejected->isRejected());
        $this->assertFalse($pending->isRejected());
    }

    public function test_is_cancelled_helper(): void
    {
        $cancelled = ConnectionRequest::factory()->cancelled()->create();
        $pending   = ConnectionRequest::factory()->create();

        $this->assertTrue($cancelled->isCancelled());
        $this->assertFalse($pending->isCancelled());
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Relationships
    // ══════════════════════════════════════════════════════════════════════════

    public function test_requester_relationship_returns_correct_user(): void
    {
        $requester = User::factory()->create();
        $recipient = User::factory()->create();
        $cr = ConnectionRequest::factory()->create([
            'requester_id' => $requester->id,
            'recipient_id' => $recipient->id,
        ]);

        $this->assertInstanceOf(User::class, $cr->requester);
        $this->assertSame($requester->id, $cr->requester->id);
    }

    public function test_recipient_relationship_returns_correct_user(): void
    {
        $requester = User::factory()->create();
        $recipient = User::factory()->create();
        $cr = ConnectionRequest::factory()->create([
            'requester_id' => $requester->id,
            'recipient_id' => $recipient->id,
        ]);

        $this->assertInstanceOf(User::class, $cr->recipient);
        $this->assertSame($recipient->id, $cr->recipient->id);
    }

    public function test_user_sent_connection_requests_relationship(): void
    {
        $requester = User::factory()->create();
        $recipient = User::factory()->create();

        ConnectionRequest::factory()->count(3)->create([
            'requester_id' => $requester->id,
            'recipient_id' => $recipient->id,
        ]);

        $this->assertCount(3, $requester->sentConnectionRequests);
        $this->assertInstanceOf(ConnectionRequest::class, $requester->sentConnectionRequests->first());
    }

    public function test_user_received_connection_requests_relationship(): void
    {
        $recipient = User::factory()->create();

        ConnectionRequest::factory()->count(2)->create([
            'recipient_id' => $recipient->id,
        ]);

        $this->assertCount(2, $recipient->receivedConnectionRequests);
        $this->assertInstanceOf(ConnectionRequest::class, $recipient->receivedConnectionRequests->first());
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Self-request: application-layer prevention (no DB constraint)
    // ══════════════════════════════════════════════════════════════════════════

    public function test_self_request_is_permitted_at_db_level_but_documented(): void
    {
        // The DB does NOT enforce requester_id != recipient_id (documented limitation
        // in migration: portable CHECK constraints differ between MySQL and SQLite).
        // The application layer (ConnectionRequestService + Policy in F4) prevents this.
        // This test documents and verifies that limitation is known and intentional.
        $user = User::factory()->create();

        $cr = ConnectionRequest::factory()->create([
            'requester_id' => $user->id,
            'recipient_id' => $user->id,
        ]);

        // DB allows it — application must prevent it
        $this->assertSame($cr->requester_id, $cr->recipient_id);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Re-request: multiple non-pending rows allowed per pair
    // ══════════════════════════════════════════════════════════════════════════

    public function test_multiple_non_pending_requests_allowed_per_user_pair(): void
    {
        // Schema intentionally has no UNIQUE(requester_id, recipient_id).
        // A user can re-request after rejection/cancellation.
        // Only one PENDING at a time is enforced at the application layer.
        $requester = User::factory()->create();
        $recipient = User::factory()->create();

        ConnectionRequest::factory()->rejected()->create([
            'requester_id' => $requester->id,
            'recipient_id' => $recipient->id,
        ]);
        ConnectionRequest::factory()->create([  // fresh pending after rejection
            'requester_id' => $requester->id,
            'recipient_id' => $recipient->id,
        ]);

        $count = ConnectionRequest::where('requester_id', $requester->id)
            ->where('recipient_id', $recipient->id)
            ->count();

        $this->assertSame(2, $count);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Existing companion-discovery relationship access on User
    // ══════════════════════════════════════════════════════════════════════════

    public function test_user_has_required_companion_discovery_relationships(): void
    {
        $user = User::factory()->create();

        // Verify all relationships needed by the future CompanionDiscoveryService exist
        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\HasOne::class,
            $user->profile()
        );
        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\BelongsToMany::class,
            $user->interests()
        );
        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\HasMany::class,
            $user->preferredDestinations()
        );
        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\HasMany::class,
            $user->travelAvailabilities()
        );
        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\HasMany::class,
            $user->sentConnectionRequests()
        );
        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\HasMany::class,
            $user->receivedConnectionRequests()
        );
    }
}
