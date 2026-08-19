<?php

namespace Tests\Feature\Trip;

use App\Enums\TripType;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 2C — Trip Discovery Tests
 *
 * Tests cover: authentication, visibility rules, all filters, pagination,
 * sorting, the upcoming rule, own-trip exclusion, and budget/date overlap semantics.
 */
class TripDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    // ── Authentication ────────────────────────────────────────────────────────

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/trips')->assertStatus(401);
    }

    public function test_authenticated_user_can_access_discovery(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/trips')
            ->assertStatus(200)
            ->assertJsonStructure([
                'success', 'message',
                'data' => ['items', 'pagination'],
            ]);
    }

    // ── Visibility rules ──────────────────────────────────────────────────────

    public function test_published_trip_appears_in_discovery(): void
    {
        $owner  = User::factory()->create();
        $viewer = User::factory()->create();
        Trip::factory()->published()->create([
            'user_id'    => $owner->id,
            'start_date' => now()->addDays(10)->toDateString(),
            'end_date'   => now()->addDays(15)->toDateString(),
        ]);

        $response = $this->actingAs($viewer, 'sanctum')->getJson('/api/trips');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.items'));
    }

    public function test_draft_trip_is_excluded(): void
    {
        $owner  = User::factory()->create();
        $viewer = User::factory()->create();
        Trip::factory()->create(['user_id' => $owner->id]); // status = draft

        $response = $this->actingAs($viewer, 'sanctum')->getJson('/api/trips');
        $this->assertCount(0, $response->json('data.items'));
    }

    public function test_cancelled_trip_is_excluded(): void
    {
        $owner  = User::factory()->create();
        $viewer = User::factory()->create();
        Trip::factory()->cancelled()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($viewer, 'sanctum')->getJson('/api/trips');
        $this->assertCount(0, $response->json('data.items'));
    }

    public function test_completed_trip_is_excluded(): void
    {
        $owner  = User::factory()->create();
        $viewer = User::factory()->create();
        Trip::factory()->completed()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($viewer, 'sanctum')->getJson('/api/trips');
        $this->assertCount(0, $response->json('data.items'));
    }

    public function test_own_trip_is_excluded_from_discovery(): void
    {
        $user = User::factory()->create();
        Trip::factory()->published()->create([
            'user_id'    => $user->id,
            'start_date' => now()->addDays(10)->toDateString(),
            'end_date'   => now()->addDays(15)->toDateString(),
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/trips');
        $this->assertCount(0, $response->json('data.items'));
    }

    public function test_other_users_published_trips_appear_in_discovery(): void
    {
        $owner  = User::factory()->create();
        $viewer = User::factory()->create();

        Trip::factory()->count(3)->published()->create([
            'user_id'    => $owner->id,
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date'   => now()->addDays(10)->toDateString(),
        ]);

        $response = $this->actingAs($viewer, 'sanctum')->getJson('/api/trips');
        $this->assertCount(3, $response->json('data.items'));
    }

    // ── Upcoming rule ─────────────────────────────────────────────────────────

    public function test_past_trip_is_excluded_by_upcoming_rule(): void
    {
        $owner  = User::factory()->create();
        $viewer = User::factory()->create();

        // Trip that fully ended yesterday
        Trip::factory()->create([
            'user_id'    => $owner->id,
            'status'     => 'published',
            'start_date' => now()->subDays(10)->toDateString(),
            'end_date'   => now()->subDay()->toDateString(),
        ]);

        $response = $this->actingAs($viewer, 'sanctum')->getJson('/api/trips');
        $this->assertCount(0, $response->json('data.items'));
    }

    public function test_ongoing_trip_ending_today_is_included(): void
    {
        $owner  = User::factory()->create();
        $viewer = User::factory()->create();

        Trip::factory()->create([
            'user_id'    => $owner->id,
            'status'     => 'published',
            'start_date' => now()->subDays(3)->toDateString(),
            'end_date'   => now()->toDateString(), // ends today — still discoverable
        ]);

        $response = $this->actingAs($viewer, 'sanctum')->getJson('/api/trips');
        $this->assertCount(1, $response->json('data.items'));
    }

    public function test_future_trip_is_included(): void
    {
        $owner  = User::factory()->create();
        $viewer = User::factory()->create();

        Trip::factory()->published()->create([
            'user_id'    => $owner->id,
            'start_date' => now()->addMonth()->toDateString(),
            'end_date'   => now()->addMonth()->addDays(5)->toDateString(),
        ]);

        $response = $this->actingAs($viewer, 'sanctum')->getJson('/api/trips');
        $this->assertCount(1, $response->json('data.items'));
    }

    // ── Destination filter ────────────────────────────────────────────────────

    public function test_destination_partial_match(): void
    {
        [$owner, $viewer] = User::factory()->count(2)->create()->all();

        Trip::factory()->published()->create([
            'user_id'     => $owner->id,
            'destination' => 'Udaipur',
            'start_date'  => now()->addDays(5)->toDateString(),
            'end_date'    => now()->addDays(10)->toDateString(),
        ]);
        Trip::factory()->published()->create([
            'user_id'     => $owner->id,
            'destination' => 'Jaipur',
            'start_date'  => now()->addDays(5)->toDateString(),
            'end_date'    => now()->addDays(10)->toDateString(),
        ]);

        $response = $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/trips?destination=Uda');

        $this->assertCount(1, $response->json('data.items'));
        $this->assertSame('Udaipur', $response->json('data.items.0.destination'));
    }

    public function test_destination_filter_is_case_insensitive(): void
    {
        [$owner, $viewer] = User::factory()->count(2)->create()->all();

        Trip::factory()->published()->create([
            'user_id'     => $owner->id,
            'destination' => 'Udaipur',
            'start_date'  => now()->addDays(5)->toDateString(),
            'end_date'    => now()->addDays(10)->toDateString(),
        ]);

        $response = $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/trips?destination=udaipur');

        $this->assertCount(1, $response->json('data.items'));
    }

    public function test_destination_filter_with_leading_trailing_whitespace(): void
    {
        [$owner, $viewer] = User::factory()->count(2)->create()->all();

        Trip::factory()->published()->create([
            'user_id'     => $owner->id,
            'destination' => 'Manali',
            'start_date'  => now()->addDays(5)->toDateString(),
            'end_date'    => now()->addDays(10)->toDateString(),
        ]);

        // Whitespace is trimmed by prepareForValidation
        $response = $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/trips?' . http_build_query(['destination' => '  Manali  ']));

        $this->assertCount(1, $response->json('data.items'));
    }

    public function test_destination_filter_returns_empty_for_no_match(): void
    {
        [$owner, $viewer] = User::factory()->count(2)->create()->all();

        Trip::factory()->published()->create([
            'user_id'    => $owner->id,
            'destination' => 'Goa',
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date'   => now()->addDays(10)->toDateString(),
        ]);

        $response = $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/trips?destination=Manali');

        $this->assertCount(0, $response->json('data.items'));
    }

    // ── Date overlap filter ───────────────────────────────────────────────────

    public function test_overlapping_trip_is_included_in_date_filter(): void
    {
        [$owner, $viewer] = User::factory()->count(2)->create()->all();

        // Trip: Sep 12 – Sep 14; filter: Sep 10 – Sep 15  (fully contained)
        Trip::factory()->published()->create([
            'user_id'    => $owner->id,
            'start_date' => '2027-09-12',
            'end_date'   => '2027-09-14',
        ]);

        $response = $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/trips?start_date=2027-09-10&end_date=2027-09-15');

        $this->assertCount(1, $response->json('data.items'));
    }

    public function test_non_overlapping_trip_is_excluded_in_date_filter(): void
    {
        [$owner, $viewer] = User::factory()->count(2)->create()->all();

        // Trip: Oct 1 – Oct 5; filter: Sep 10 – Sep 15  (no overlap)
        Trip::factory()->published()->create([
            'user_id'    => $owner->id,
            'start_date' => '2027-10-01',
            'end_date'   => '2027-10-05',
        ]);

        $response = $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/trips?start_date=2027-09-10&end_date=2027-09-15');

        $this->assertCount(0, $response->json('data.items'));
    }

    public function test_partially_overlapping_trip_is_included(): void
    {
        [$owner, $viewer] = User::factory()->count(2)->create()->all();

        // Trip: Sep 13 – Sep 20; filter: Sep 10 – Sep 15  (partial overlap — trip starts within window)
        Trip::factory()->published()->create([
            'user_id'    => $owner->id,
            'start_date' => '2027-09-13',
            'end_date'   => '2027-09-20',
        ]);

        $response = $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/trips?start_date=2027-09-10&end_date=2027-09-15');

        $this->assertCount(1, $response->json('data.items'));
    }

    public function test_same_day_overlap_is_included(): void
    {
        [$owner, $viewer] = User::factory()->count(2)->create()->all();

        // Trip ends exactly on the requested start — still overlaps (trip.end >= requested.start)
        Trip::factory()->published()->create([
            'user_id'    => $owner->id,
            'start_date' => '2027-09-08',
            'end_date'   => '2027-09-10',
        ]);

        $response = $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/trips?start_date=2027-09-10&end_date=2027-09-15');

        $this->assertCount(1, $response->json('data.items'));
    }

    public function test_invalid_date_range_returns_422(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/trips?start_date=2027-09-20&end_date=2027-09-10')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['end_date']);
    }

    // ── Budget overlap filter ─────────────────────────────────────────────────

    public function test_overlapping_budget_trip_is_included(): void
    {
        [$owner, $viewer] = User::factory()->count(2)->create()->all();

        // Trip: 5000–8000; filter: 6000–7000 (filter inside trip range)
        Trip::factory()->published()->create([
            'user_id'    => $owner->id,
            'budget_min' => 5000,
            'budget_max' => 8000,
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date'   => now()->addDays(10)->toDateString(),
        ]);

        $response = $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/trips?budget_min=6000&budget_max=7000');

        $this->assertCount(1, $response->json('data.items'));
    }

    public function test_non_overlapping_budget_trip_is_excluded(): void
    {
        [$owner, $viewer] = User::factory()->count(2)->create()->all();

        // Trip: 5000–8000; filter: 10000–15000 (no overlap)
        Trip::factory()->published()->create([
            'user_id'    => $owner->id,
            'budget_min' => 5000,
            'budget_max' => 8000,
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date'   => now()->addDays(10)->toDateString(),
        ]);

        $response = $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/trips?budget_min=10000&budget_max=15000');

        $this->assertCount(0, $response->json('data.items'));
    }

    public function test_trip_with_null_budget_is_always_included_in_budget_filter(): void
    {
        [$owner, $viewer] = User::factory()->count(2)->create()->all();

        // Trip has no budget set — should always show up
        Trip::factory()->published()->create([
            'user_id'    => $owner->id,
            'budget_min' => null,
            'budget_max' => null,
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date'   => now()->addDays(10)->toDateString(),
        ]);

        $response = $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/trips?budget_min=5000&budget_max=8000');

        $this->assertCount(1, $response->json('data.items'));
    }

    public function test_invalid_budget_range_returns_422(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/trips?budget_min=10000&budget_max=5000')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['budget_max']);
    }

    // ── Trip type filter ──────────────────────────────────────────────────────

    public function test_valid_trip_type_filters_correctly(): void
    {
        [$owner, $viewer] = User::factory()->count(2)->create()->all();

        Trip::factory()->published()->create([
            'user_id'    => $owner->id,
            'trip_type'  => TripType::Adventure->value,
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date'   => now()->addDays(10)->toDateString(),
        ]);
        Trip::factory()->published()->create([
            'user_id'    => $owner->id,
            'trip_type'  => TripType::Beach->value,
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date'   => now()->addDays(10)->toDateString(),
        ]);

        $response = $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/trips?trip_type=adventure');

        $this->assertCount(1, $response->json('data.items'));
        $this->assertSame('adventure', $response->json('data.items.0.trip_type'));
    }

    public function test_invalid_trip_type_returns_422(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/trips?trip_type=invalid_type')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['trip_type']);
    }

    // ── Pagination ────────────────────────────────────────────────────────────

    public function test_discovery_is_paginated_with_default_page_size(): void
    {
        [$owner, $viewer] = User::factory()->count(2)->create()->all();

        Trip::factory()->count(25)->published()->create([
            'user_id'    => $owner->id,
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date'   => now()->addDays(10)->toDateString(),
        ]);

        $response = $this->actingAs($viewer, 'sanctum')->getJson('/api/trips');

        $response->assertStatus(200);
        $this->assertCount(20, $response->json('data.items')); // default per_page=20
        $this->assertSame(25, $response->json('data.pagination.total'));
        $this->assertSame(20, $response->json('data.pagination.per_page'));
        $this->assertSame(2, $response->json('data.pagination.last_page'));
        $this->assertTrue($response->json('data.pagination.has_more'));
    }

    public function test_second_page_returns_remaining_items(): void
    {
        [$owner, $viewer] = User::factory()->count(2)->create()->all();

        Trip::factory()->count(25)->published()->create([
            'user_id'    => $owner->id,
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date'   => now()->addDays(10)->toDateString(),
        ]);

        $response = $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/trips?page=2&per_page=20');

        $this->assertCount(5, $response->json('data.items'));
        $this->assertFalse($response->json('data.pagination.has_more'));
    }

    public function test_custom_per_page_is_respected(): void
    {
        [$owner, $viewer] = User::factory()->count(2)->create()->all();

        Trip::factory()->count(10)->published()->create([
            'user_id'    => $owner->id,
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date'   => now()->addDays(10)->toDateString(),
        ]);

        $response = $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/trips?per_page=5');

        $this->assertCount(5, $response->json('data.items'));
        $this->assertSame(5, $response->json('data.pagination.per_page'));
    }

    public function test_per_page_maximum_is_enforced(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/trips?per_page=100')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_page_must_be_positive(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/trips?page=0')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['page']);
    }

    // ── Sorting ───────────────────────────────────────────────────────────────

    public function test_default_sort_is_start_date_ascending(): void
    {
        [$owner, $viewer] = User::factory()->count(2)->create()->all();

        Trip::factory()->published()->create([
            'user_id'    => $owner->id,
            'start_date' => now()->addDays(10)->toDateString(),
            'end_date'   => now()->addDays(15)->toDateString(),
        ]);
        Trip::factory()->published()->create([
            'user_id'    => $owner->id,
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date'   => now()->addDays(8)->toDateString(),
        ]);

        $response = $this->actingAs($viewer, 'sanctum')->getJson('/api/trips');

        $dates = collect($response->json('data.items'))->pluck('start_date');
        $this->assertSame($dates->sort()->values()->all(), $dates->values()->all());
    }

    public function test_newest_sort_returns_most_recently_created_first(): void
    {
        [$owner, $viewer] = User::factory()->count(2)->create()->all();

        $older = Trip::factory()->published()->create([
            'user_id'     => $owner->id,
            'start_date'  => now()->addDays(5)->toDateString(),
            'end_date'    => now()->addDays(10)->toDateString(),
            'created_at'  => now()->subHour(),  // explicitly older
        ]);
        $newer = Trip::factory()->published()->create([
            'user_id'     => $owner->id,
            'start_date'  => now()->addDays(5)->toDateString(),
            'end_date'    => now()->addDays(10)->toDateString(),
            'created_at'  => now(),             // explicitly newer
        ]);

        $response = $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/trips?sort=newest');

        $ids = collect($response->json('data.items'))->pluck('id')->all();
        // newer should appear before older
        $this->assertSame($newer->id, $ids[0]);
        $this->assertSame($older->id, $ids[1]);
    }

    public function test_start_date_sort_works_explicitly(): void
    {
        [$owner, $viewer] = User::factory()->count(2)->create()->all();

        Trip::factory()->published()->create([
            'user_id'    => $owner->id,
            'start_date' => now()->addDays(20)->toDateString(),
            'end_date'   => now()->addDays(25)->toDateString(),
        ]);
        Trip::factory()->published()->create([
            'user_id'    => $owner->id,
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date'   => now()->addDays(8)->toDateString(),
        ]);

        $response = $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/trips?sort=start_date');

        $dates = collect($response->json('data.items'))->pluck('start_date');
        $this->assertSame($dates->sort()->values()->all(), $dates->values()->all());
    }

    public function test_invalid_sort_returns_422(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/trips?sort=user_id')   // arbitrary column — rejected
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sort']);
    }

    // ── Response structure ────────────────────────────────────────────────────

    public function test_discovery_response_includes_all_required_fields(): void
    {
        [$owner, $viewer] = User::factory()->count(2)->create()->all();

        Trip::factory()->published()->create([
            'user_id'    => $owner->id,
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date'   => now()->addDays(10)->toDateString(),
        ]);

        $response = $this->actingAs($viewer, 'sanctum')->getJson('/api/trips');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'items' => [
                        '*' => [
                            'id', 'title', 'destination', 'status', 'trip_type',
                            'start_date', 'end_date', 'max_members',
                            'member_count', 'remaining_slots', 'owner',
                        ],
                    ],
                    'pagination' => [
                        'total', 'per_page', 'current_page', 'last_page', 'has_more',
                    ],
                ],
            ]);
    }

    public function test_discovery_does_not_show_status_other_than_published(): void
    {
        [$owner, $viewer] = User::factory()->count(2)->create()->all();

        // One discoverable, one not
        Trip::factory()->published()->create([
            'user_id'    => $owner->id,
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date'   => now()->addDays(10)->toDateString(),
        ]);
        Trip::factory()->create(['user_id' => $owner->id]); // draft

        $response = $this->actingAs($viewer, 'sanctum')->getJson('/api/trips');

        $statuses = collect($response->json('data.items'))->pluck('status')->unique()->all();
        $this->assertSame(['published'], $statuses);
    }

    // ── Combined filters ──────────────────────────────────────────────────────

    public function test_multiple_filters_combine_correctly(): void
    {
        [$owner, $viewer] = User::factory()->count(2)->create()->all();

        // Matching trip: adventure, Manali, future
        Trip::factory()->published()->create([
            'user_id'     => $owner->id,
            'destination' => 'Manali',
            'trip_type'   => TripType::Adventure->value,
            'start_date'  => now()->addDays(5)->toDateString(),
            'end_date'    => now()->addDays(10)->toDateString(),
        ]);
        // Non-matching trip: beach, different destination
        Trip::factory()->published()->create([
            'user_id'     => $owner->id,
            'destination' => 'Goa',
            'trip_type'   => TripType::Beach->value,
            'start_date'  => now()->addDays(5)->toDateString(),
            'end_date'    => now()->addDays(10)->toDateString(),
        ]);

        $response = $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/trips?destination=Man&trip_type=adventure');

        $this->assertCount(1, $response->json('data.items'));
        $this->assertSame('Manali', $response->json('data.items.0.destination'));
    }
}
