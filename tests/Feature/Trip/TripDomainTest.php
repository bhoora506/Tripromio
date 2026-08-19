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

/**
 * Phase 2A — Trip Domain Foundation Tests
 *
 * Covers: relationships, DB constraints, enums, factory integrity.
 * Does NOT test API endpoints (those are Phase 2B).
 */
class TripDomainTest extends TestCase
{
    use RefreshDatabase;

    // ── Factory Integrity ──────────────────────────────────────────────────────

    public function test_trip_factory_creates_valid_trip(): void
    {
        $trip = Trip::factory()->create();

        $this->assertNotNull($trip->id);
        $this->assertNotNull($trip->user_id);
        $this->assertNotNull($trip->title);
        $this->assertNotNull($trip->destination);
        $this->assertNotNull($trip->start_date);
        $this->assertNotNull($trip->end_date);
        $this->assertTrue($trip->end_date >= $trip->start_date);
        $this->assertInstanceOf(TripStatus::class, $trip->status);
        $this->assertInstanceOf(TripType::class, $trip->trip_type);
        $this->assertSame(TripStatus::Draft, $trip->status);
        $this->assertGreaterThanOrEqual(2, $trip->max_members);
    }

    public function test_trip_factory_budget_max_is_never_less_than_budget_min(): void
    {
        // Run multiple factory instances to reduce false-positive risk
        for ($i = 0; $i < 5; $i++) {
            $trip = Trip::factory()->create();
            if ($trip->budget_min !== null && $trip->budget_max !== null) {
                $this->assertGreaterThanOrEqual(
                    (float) $trip->budget_min,
                    (float) $trip->budget_max,
                    'budget_max must be >= budget_min'
                );
            }
        }
    }

    public function test_trip_factory_status_states(): void
    {
        $draft     = Trip::factory()->create();
        $published = Trip::factory()->published()->create();
        $ongoing   = Trip::factory()->ongoing()->create();
        $completed = Trip::factory()->completed()->create();
        $cancelled = Trip::factory()->cancelled()->create();

        $this->assertSame(TripStatus::Draft,     $draft->status);
        $this->assertSame(TripStatus::Published,  $published->status);
        $this->assertSame(TripStatus::Ongoing,    $ongoing->status);
        $this->assertSame(TripStatus::Completed,  $completed->status);
        $this->assertSame(TripStatus::Cancelled,  $cancelled->status);
    }

    public function test_trip_member_factory_creates_valid_member(): void
    {
        $member = TripMember::factory()->create();

        $this->assertNotNull($member->id);
        $this->assertInstanceOf(MemberRole::class, $member->role);
        $this->assertInstanceOf(MemberStatus::class, $member->status);
        $this->assertSame(MemberRole::Member,   $member->role);
        $this->assertSame(MemberStatus::Active, $member->status);
        $this->assertNotNull($member->joined_at);
    }

    public function test_trip_member_factory_owner_state(): void
    {
        $owner = TripMember::factory()->owner()->create();

        $this->assertSame(MemberRole::Owner, $owner->role);
        $this->assertNull($owner->joined_at); // owner doesn't "join"
    }

    // ── Enum Behaviour ─────────────────────────────────────────────────────────

    public function test_trip_status_enum_values_are_correct(): void
    {
        $this->assertSame(['draft', 'published', 'ongoing', 'completed', 'cancelled'],
            TripStatus::values());
    }

    public function test_trip_type_enum_values_are_correct(): void
    {
        $this->assertContains('weekend',     TripType::values());
        $this->assertContains('adventure',   TripType::values());
        $this->assertContains('backpacking', TripType::values());
        $this->assertContains('road_trip',   TripType::values());
        $this->assertContains('other',       TripType::values());
        $this->assertCount(10,               TripType::values());
    }

    public function test_trip_status_transition_logic(): void
    {
        // Valid transitions
        $this->assertTrue(TripStatus::Draft->canTransitionTo(TripStatus::Published));
        $this->assertTrue(TripStatus::Draft->canTransitionTo(TripStatus::Cancelled));
        $this->assertTrue(TripStatus::Published->canTransitionTo(TripStatus::Ongoing));
        $this->assertTrue(TripStatus::Published->canTransitionTo(TripStatus::Cancelled));
        $this->assertTrue(TripStatus::Ongoing->canTransitionTo(TripStatus::Completed));
        $this->assertTrue(TripStatus::Ongoing->canTransitionTo(TripStatus::Cancelled));

        // Invalid transitions
        $this->assertFalse(TripStatus::Draft->canTransitionTo(TripStatus::Ongoing));
        $this->assertFalse(TripStatus::Draft->canTransitionTo(TripStatus::Completed));
        $this->assertFalse(TripStatus::Completed->canTransitionTo(TripStatus::Draft));
        $this->assertFalse(TripStatus::Cancelled->canTransitionTo(TripStatus::Published));
        $this->assertFalse(TripStatus::Published->canTransitionTo(TripStatus::Draft));
    }

    public function test_trip_status_terminal_states(): void
    {
        $this->assertTrue(TripStatus::Completed->isTerminal());
        $this->assertTrue(TripStatus::Cancelled->isTerminal());
        $this->assertFalse(TripStatus::Draft->isTerminal());
        $this->assertFalse(TripStatus::Published->isTerminal());
        $this->assertFalse(TripStatus::Ongoing->isTerminal());
    }

    public function test_member_role_enum_values(): void
    {
        $this->assertSame(['owner', 'member'], MemberRole::values());
    }

    public function test_member_status_enum_values(): void
    {
        $this->assertSame(['active', 'left', 'removed'], MemberStatus::values());
    }

    // ── Model Casts ────────────────────────────────────────────────────────────

    public function test_trip_enums_are_cast_correctly(): void
    {
        $trip = Trip::factory()->published()->create([
            'trip_type' => TripType::Adventure->value,
        ]);

        $fresh = Trip::find($trip->id);
        $this->assertInstanceOf(TripStatus::class, $fresh->status);
        $this->assertInstanceOf(TripType::class,   $fresh->trip_type);
        $this->assertSame(TripStatus::Published, $fresh->status);
        $this->assertSame(TripType::Adventure,   $fresh->trip_type);
    }

    public function test_trip_dates_are_cast_to_carbon(): void
    {
        $trip = Trip::factory()->create([
            'start_date' => '2026-10-01',
            'end_date'   => '2026-10-05',
        ]);

        $fresh = Trip::find($trip->id);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $fresh->start_date);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $fresh->end_date);
        $this->assertSame('2026-10-01', $fresh->start_date->format('Y-m-d'));
        $this->assertSame('2026-10-05', $fresh->end_date->format('Y-m-d'));
    }

    public function test_trip_member_enums_are_cast_correctly(): void
    {
        $member = TripMember::factory()->owner()->create();

        $fresh = TripMember::find($member->id);
        $this->assertInstanceOf(MemberRole::class,   $fresh->role);
        $this->assertInstanceOf(MemberStatus::class, $fresh->status);
    }

    // ── Relationships ──────────────────────────────────────────────────────────

    public function test_trip_belongs_to_owner(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $trip->owner);
        $this->assertSame($user->id, $trip->owner->id);
    }

    public function test_user_has_trips(): void
    {
        $user = User::factory()->create();
        Trip::factory()->count(3)->create(['user_id' => $user->id]);

        $this->assertCount(3, $user->trips);
        $this->assertInstanceOf(Trip::class, $user->trips->first());
    }

    public function test_trip_has_trip_members(): void
    {
        $trip  = Trip::factory()->create();
        $owner = TripMember::factory()->owner()->create(['trip_id' => $trip->id]);
        $mem1  = TripMember::factory()->create(['trip_id' => $trip->id]);

        $this->assertCount(2, $trip->tripMembers);
        $this->assertInstanceOf(TripMember::class, $trip->tripMembers->first());
    }

    public function test_trip_member_belongs_to_trip_and_user(): void
    {
        $user   = User::factory()->create();
        $trip   = Trip::factory()->create();
        $member = TripMember::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $user->id,
        ]);

        $this->assertInstanceOf(Trip::class, $member->trip);
        $this->assertInstanceOf(User::class, $member->user);
        $this->assertSame($trip->id, $member->trip->id);
        $this->assertSame($user->id, $member->user->id);
    }

    public function test_user_trip_members_relationship(): void
    {
        $user  = User::factory()->create();
        $trip1 = Trip::factory()->create();
        $trip2 = Trip::factory()->create();

        TripMember::factory()->owner()->create(['trip_id' => $trip1->id, 'user_id' => $user->id]);
        TripMember::factory()->create(['trip_id' => $trip2->id, 'user_id' => $user->id]);

        $this->assertCount(2, $user->tripMembers);
    }

    public function test_user_trips_joined_relationship(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create();
        TripMember::factory()->create(['trip_id' => $trip->id, 'user_id' => $user->id]);

        $joined = $user->tripsJoined;
        $this->assertCount(1, $joined);
        $this->assertInstanceOf(Trip::class, $joined->first());
    }

    public function test_trip_active_members_relationship_excludes_inactive(): void
    {
        $trip = Trip::factory()->create();
        TripMember::factory()->owner()->create(['trip_id' => $trip->id]);
        TripMember::factory()->create(['trip_id' => $trip->id]);           // active member
        TripMember::factory()->left()->create(['trip_id' => $trip->id]);   // left
        TripMember::factory()->removed()->create(['trip_id' => $trip->id]); // removed

        $this->assertCount(4, $trip->tripMembers);       // all rows
        $this->assertCount(2, $trip->activeMembers);     // only active ones
    }

    // ── DB Constraints ─────────────────────────────────────────────────────────

    public function test_duplicate_trip_membership_is_rejected(): void
    {
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        $user = User::factory()->create();
        $trip = Trip::factory()->create();

        TripMember::factory()->create(['trip_id' => $trip->id, 'user_id' => $user->id]);
        // Second row with same trip_id + user_id must fail
        TripMember::factory()->create(['trip_id' => $trip->id, 'user_id' => $user->id]);
    }

    public function test_trip_member_status_helpers(): void
    {
        $ownerMember  = TripMember::factory()->owner()->create();
        $activeMember = TripMember::factory()->create();
        $leftMember   = TripMember::factory()->left()->create();

        $this->assertTrue($ownerMember->isOwner());
        $this->assertFalse($activeMember->isOwner());

        $this->assertTrue($activeMember->isActive());
        $this->assertFalse($leftMember->isActive());
    }

    // ── Remaining Slots Helper ─────────────────────────────────────────────────

    public function test_trip_remaining_slots_calculation(): void
    {
        $trip = Trip::factory()->create(['max_members' => 3]);

        // No members yet
        $this->assertSame(3, $trip->remainingSlots());
        $this->assertTrue($trip->hasOpenSlots());

        // Add owner (active)
        TripMember::factory()->owner()->create(['trip_id' => $trip->id]);
        $this->assertSame(2, $trip->fresh()->remainingSlots());

        // Add another active member
        TripMember::factory()->create(['trip_id' => $trip->id]);
        $this->assertSame(1, $trip->fresh()->remainingSlots());

        // Fill last slot
        TripMember::factory()->create(['trip_id' => $trip->id]);
        $this->assertSame(0, $trip->fresh()->remainingSlots());
        $this->assertFalse($trip->fresh()->hasOpenSlots());
    }
}
