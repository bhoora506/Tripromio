<?php

namespace Tests\Feature\Companion;

use App\Models\Interest;
use App\Models\TravelAvailability;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\PreferredDestination;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F3 API Tests — Companion Discovery Endpoint.
 *
 * Covers all 32 scenarios specified in the F3 requirements.
 *
 * Profile creation convention:
 *   UserProfile has no factory; using UserProfile::create() / User::profile()->create()
 *   matching the convention used by all existing profile tests.
 *
 * Profile completion gate:
 *   The service requires at least one of bio/city/country/travel_style to be set.
 *   Tests that need a "gated out" user leave all four NULL.
 *   Tests that need a "gated in" user set at least one field.
 */
class CompanionDiscoveryApiTest extends TestCase
{
    use RefreshDatabase;

    private string $url = '/api/companions';

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Create a user with a minimal discoverable profile (passes completion gate).
     */
    private function discoverableUser(array $profileAttrs = []): User
    {
        $user = User::factory()->create();
        $user->profile()->create(array_merge([
            'bio'             => 'I love to travel.',
            'is_discoverable' => true,
        ], $profileAttrs));
        return $user;
    }

    /**
     * Create a user with a non-discoverable profile.
     */
    private function hiddenUser(): User
    {
        $user = User::factory()->create();
        $user->profile()->create([
            'bio'             => 'Hidden traveller',
            'is_discoverable' => false,
        ]);
        return $user;
    }

    /**
     * Create a user whose profile exists but fails the completion gate
     * (all four gate fields are NULL).
     */
    private function emptyProfileUser(): User
    {
        $user = User::factory()->create();
        $user->profile()->create([
            'bio'             => null,
            'city'            => null,
            'country'         => null,
            'travel_style'    => null,
            'is_discoverable' => true,
        ]);
        return $user;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 1. Authenticated user can access /api/companions
    // ══════════════════════════════════════════════════════════════════════════

    public function test_authenticated_user_can_access_companions(): void
    {
        $user = $this->discoverableUser();

        $this->actingAs($user, 'sanctum')
            ->getJson($this->url)
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => ['items', 'pagination'],
            ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 2. Unauthenticated request returns 401
    // ══════════════════════════════════════════════════════════════════════════

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson($this->url)->assertStatus(401);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 3. Authenticated user is excluded from results
    // ══════════════════════════════════════════════════════════════════════════

    public function test_authenticated_user_is_excluded_from_results(): void
    {
        $me    = $this->discoverableUser();
        $other = $this->discoverableUser();

        $response = $this->actingAs($me, 'sanctum')->getJson($this->url);
        $ids = collect($response->json('data.items'))->pluck('id');

        $this->assertNotContains($me->id, $ids);
        $this->assertContains($other->id, $ids);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 4. Users without profiles are excluded
    // ══════════════════════════════════════════════════════════════════════════

    public function test_users_without_profiles_are_excluded(): void
    {
        $me          = $this->discoverableUser();
        $noProfile   = User::factory()->create(); // no UserProfile row

        $response = $this->actingAs($me, 'sanctum')->getJson($this->url);
        $ids = collect($response->json('data.items'))->pluck('id');

        $this->assertNotContains($noProfile->id, $ids);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 5. Non-discoverable users are excluded
    // ══════════════════════════════════════════════════════════════════════════

    public function test_non_discoverable_users_are_excluded(): void
    {
        $me     = $this->discoverableUser();
        $hidden = $this->hiddenUser();

        $response = $this->actingAs($me, 'sanctum')->getJson($this->url);
        $ids = collect($response->json('data.items'))->pluck('id');

        $this->assertNotContains($hidden->id, $ids);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 6. Profile completion gate excludes empty profiles
    // ══════════════════════════════════════════════════════════════════════════

    public function test_empty_profile_users_are_excluded_by_completion_gate(): void
    {
        $me    = $this->discoverableUser();
        $empty = $this->emptyProfileUser();

        $response = $this->actingAs($me, 'sanctum')->getJson($this->url);
        $ids = collect($response->json('data.items'))->pluck('id');

        $this->assertNotContains($empty->id, $ids);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 7. Default pagination works
    // ══════════════════════════════════════════════════════════════════════════

    public function test_default_pagination_returns_correct_structure(): void
    {
        $me = $this->discoverableUser();
        $this->discoverableUser();
        $this->discoverableUser();

        $response = $this->actingAs($me, 'sanctum')->getJson($this->url);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'items',
                    'pagination' => [
                        'total', 'per_page', 'current_page', 'last_page', 'has_more',
                    ],
                ],
            ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 8. per_page is respected
    // ══════════════════════════════════════════════════════════════════════════

    public function test_per_page_is_respected(): void
    {
        $me = $this->discoverableUser();
        for ($i = 0; $i < 5; $i++) {
            $this->discoverableUser();
        }

        $response = $this->actingAs($me, 'sanctum')
            ->getJson($this->url . '?per_page=2');

        $response->assertStatus(200)
            ->assertJsonPath('data.pagination.per_page', 2)
            ->assertJsonCount(2, 'data.items');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 9. per_page maximum of 50 is enforced
    // ══════════════════════════════════════════════════════════════════════════

    public function test_per_page_maximum_is_enforced(): void
    {
        $me = $this->discoverableUser();

        $response = $this->actingAs($me, 'sanctum')
            ->getJson($this->url . '?per_page=100');

        $response->assertStatus(422);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 10. page parameter works
    // ══════════════════════════════════════════════════════════════════════════

    public function test_page_pagination_works(): void
    {
        $me = $this->discoverableUser();
        for ($i = 0; $i < 4; $i++) {
            $this->discoverableUser();
        }

        $response = $this->actingAs($me, 'sanctum')
            ->getJson($this->url . '?per_page=2&page=2');

        $response->assertStatus(200)
            ->assertJsonPath('data.pagination.current_page', 2)
            ->assertJsonCount(2, 'data.items');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 11. Default sort is profile_completion descending
    // ══════════════════════════════════════════════════════════════════════════

    public function test_default_sort_is_profile_completion_descending(): void
    {
        $me = $this->discoverableUser();

        // rich: bio + city + country + travel_style → 50 points (without photo/lang/interests)
        $rich = User::factory()->create();
        $rich->profile()->create([
            'bio'             => 'Rich profile',
            'city'            => 'Delhi',
            'country'         => 'India',
            'travel_style'    => 'adventure',
            'is_discoverable' => true,
        ]);

        // sparse: bio only → 15 points
        $sparse = User::factory()->create();
        $sparse->profile()->create([
            'bio'             => 'Sparse',
            'is_discoverable' => true,
        ]);

        $response = $this->actingAs($me, 'sanctum')->getJson($this->url);
        $ids = collect($response->json('data.items'))->pluck('id')->toArray();

        $richPos   = array_search($rich->id, $ids);
        $sparsePos = array_search($sparse->id, $ids);

        $this->assertLessThan($sparsePos, $richPos, 'Rich profile should appear before sparse profile');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 12. newest sort works
    // ══════════════════════════════════════════════════════════════════════════

    public function test_newest_sort_returns_newest_users_first(): void
    {
        $me    = $this->discoverableUser();

        $older = User::factory()->create(['created_at' => now()->subDays(5)]);
        $older->profile()->create(['bio' => 'old', 'is_discoverable' => true]);

        $newer = User::factory()->create(['created_at' => now()]);
        $newer->profile()->create(['bio' => 'new', 'is_discoverable' => true]);

        $response = $this->actingAs($me, 'sanctum')->getJson($this->url . '?sort=newest');
        $ids = collect($response->json('data.items'))->pluck('id')->toArray();

        $newerPos = array_search($newer->id, $ids);
        $olderPos = array_search($older->id, $ids);

        $this->assertLessThan($olderPos, $newerPos, 'Newer user should appear before older user');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 13. destination filter works
    // ══════════════════════════════════════════════════════════════════════════

    public function test_destination_filter_works(): void
    {
        $me = $this->discoverableUser();

        $manali = $this->discoverableUser();
        PreferredDestination::create([
            'user_id'     => $manali->id,
            'destination' => 'Manali',
        ]);

        $goa = $this->discoverableUser();
        PreferredDestination::create([
            'user_id'     => $goa->id,
            'destination' => 'Goa',
        ]);

        $response = $this->actingAs($me, 'sanctum')
            ->getJson($this->url . '?destination=Manali');

        $ids = collect($response->json('data.items'))->pluck('id');
        $this->assertContains($manali->id, $ids);
        $this->assertNotContains($goa->id, $ids);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 14. place_id filter works
    // ══════════════════════════════════════════════════════════════════════════

    public function test_place_id_filter_works(): void
    {
        $me = $this->discoverableUser();

        $manali = $this->discoverableUser();
        PreferredDestination::create([
            'user_id'     => $manali->id,
            'destination' => 'Manali',
            'place_id'    => 'ChIJmanali123',
        ]);

        $goa = $this->discoverableUser();
        PreferredDestination::create([
            'user_id'     => $goa->id,
            'destination' => 'Goa',
            'place_id'    => 'ChIJgoa456',
        ]);

        $response = $this->actingAs($me, 'sanctum')
            ->getJson($this->url . '?place_id=ChIJmanali123');

        $ids = collect($response->json('data.items'))->pluck('id');
        $this->assertContains($manali->id, $ids);
        $this->assertNotContains($goa->id, $ids);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 15. date availability overlap filter includes overlapping companions
    // ══════════════════════════════════════════════════════════════════════════

    public function test_date_filter_includes_overlapping_availability(): void
    {
        $me = $this->discoverableUser();

        $companion = $this->discoverableUser();
        TravelAvailability::factory()->create([
            'user_id'    => $companion->id,
            'start_date' => '2027-10-01',
            'end_date'   => '2027-10-20',
        ]);

        $response = $this->actingAs($me, 'sanctum')
            ->getJson($this->url . '?start_date=2027-10-05&end_date=2027-10-15');

        $ids = collect($response->json('data.items'))->pluck('id');
        $this->assertContains($companion->id, $ids);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 16. date filter excludes non-overlapping availability
    // ══════════════════════════════════════════════════════════════════════════

    public function test_date_filter_excludes_non_overlapping_availability(): void
    {
        $me = $this->discoverableUser();

        $companion = $this->discoverableUser();
        TravelAvailability::factory()->create([
            'user_id'    => $companion->id,
            'start_date' => '2027-09-01',
            'end_date'   => '2027-09-15',
        ]);

        // Request October — no overlap
        $response = $this->actingAs($me, 'sanctum')
            ->getJson($this->url . '?start_date=2027-10-01&end_date=2027-10-31');

        $ids = collect($response->json('data.items'))->pluck('id');
        $this->assertNotContains($companion->id, $ids);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 17. travel_style filter works
    // ══════════════════════════════════════════════════════════════════════════

    public function test_travel_style_filter_works(): void
    {
        $me = $this->discoverableUser();

        $adventurer = $this->discoverableUser(['travel_style' => 'adventure']);
        $luxury     = $this->discoverableUser(['travel_style' => 'luxury']);

        $response = $this->actingAs($me, 'sanctum')
            ->getJson($this->url . '?travel_style=adventure');

        $ids = collect($response->json('data.items'))->pluck('id');
        $this->assertContains($adventurer->id, $ids);
        $this->assertNotContains($luxury->id, $ids);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 18. interest_ids filter works (at least one shared interest)
    // ══════════════════════════════════════════════════════════════════════════

    public function test_interest_ids_filter_works(): void
    {
        $me     = $this->discoverableUser();
        $hiker  = $this->discoverableUser();
        $reader = $this->discoverableUser();

        $hiking  = Interest::create(['name' => 'Hiking',  'slug' => 'hiking']);
        $reading = Interest::create(['name' => 'Reading', 'slug' => 'reading']);

        $hiker->interests()->attach($hiking);
        $reader->interests()->attach($reading);

        $response = $this->actingAs($me, 'sanctum')
            ->getJson($this->url . '?interest_ids[]=' . $hiking->id);

        $ids = collect($response->json('data.items'))->pluck('id');
        $this->assertContains($hiker->id, $ids);
        $this->assertNotContains($reader->id, $ids);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 19. Multiple interest IDs do not duplicate users
    // ══════════════════════════════════════════════════════════════════════════

    public function test_multiple_interest_ids_do_not_duplicate_users(): void
    {
        $me   = $this->discoverableUser();
        $user = $this->discoverableUser();

        $hiking  = Interest::create(['name' => 'Hiking2',  'slug' => 'hiking2']);
        $cooking = Interest::create(['name' => 'Cooking2', 'slug' => 'cooking2']);

        $user->interests()->attach([$hiking->id, $cooking->id]);

        $response = $this->actingAs($me, 'sanctum')
            ->getJson($this->url . '?interest_ids[]=' . $hiking->id . '&interest_ids[]=' . $cooking->id);

        $ids = collect($response->json('data.items'))->pluck('id');
        $this->assertSame(1, $ids->filter(fn ($id) => $id === $user->id)->count());
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 20. Invalid date range returns 422
    // ══════════════════════════════════════════════════════════════════════════

    public function test_invalid_date_range_returns_422(): void
    {
        $me = $this->discoverableUser();

        $this->actingAs($me, 'sanctum')
            ->getJson($this->url . '?start_date=2027-10-20&end_date=2027-10-01')
            ->assertStatus(422);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 21. Invalid travel_style returns 422
    // ══════════════════════════════════════════════════════════════════════════

    public function test_invalid_travel_style_returns_422(): void
    {
        $me = $this->discoverableUser();

        $this->actingAs($me, 'sanctum')
            ->getJson($this->url . '?travel_style=unknown_style')
            ->assertStatus(422);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 22. Non-existent interest ID returns 422
    // ══════════════════════════════════════════════════════════════════════════

    public function test_non_existent_interest_id_returns_422(): void
    {
        $me = $this->discoverableUser();

        $this->actingAs($me, 'sanctum')
            ->getJson($this->url . '?interest_ids[]=99999')
            ->assertStatus(422);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 23. Invalid sort returns 422
    // ══════════════════════════════════════════════════════════════════════════

    public function test_invalid_sort_returns_422(): void
    {
        $me = $this->discoverableUser();

        $this->actingAs($me, 'sanctum')
            ->getJson($this->url . '?sort=invalid_sort')
            ->assertStatus(422);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 24. Response uses CompanionResource (safe shape)
    // ══════════════════════════════════════════════════════════════════════════

    public function test_response_uses_safe_companion_resource(): void
    {
        $me    = $this->discoverableUser();
        $other = $this->discoverableUser(['city' => 'Mumbai', 'travel_style' => 'adventure']);

        $response = $this->actingAs($me, 'sanctum')->getJson($this->url);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'items' => [
                        '*' => [
                            'id', 'name', 'profile_photo_url',
                            'bio', 'city', 'country', 'languages',
                            'travel_style', 'interests', 'preferred_destinations',
                        ],
                    ],
                ],
            ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 25. Response does NOT expose email
    // ══════════════════════════════════════════════════════════════════════════

    public function test_response_does_not_expose_email(): void
    {
        $me    = $this->discoverableUser();
        $other = $this->discoverableUser();

        $response = $this->actingAs($me, 'sanctum')->getJson($this->url);
        $item = collect($response->json('data.items'))->firstWhere('id', $other->id);

        $this->assertArrayNotHasKey('email', $item);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 26. Response does NOT expose email_verified_at
    // ══════════════════════════════════════════════════════════════════════════

    public function test_response_does_not_expose_email_verified_at(): void
    {
        $me    = $this->discoverableUser();
        $other = $this->discoverableUser();

        $response = $this->actingAs($me, 'sanctum')->getJson($this->url);
        $item = collect($response->json('data.items'))->firstWhere('id', $other->id);

        $this->assertArrayNotHasKey('email_verified_at', $item);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 27. Response does NOT expose budget fields
    // ══════════════════════════════════════════════════════════════════════════

    public function test_response_does_not_expose_budget(): void
    {
        $me    = $this->discoverableUser();
        $other = $this->discoverableUser();

        $response = $this->actingAs($me, 'sanctum')->getJson($this->url);
        $item = collect($response->json('data.items'))->firstWhere('id', $other->id);

        $this->assertArrayNotHasKey('preferred_budget_min', $item);
        $this->assertArrayNotHasKey('preferred_budget_max', $item);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 28. Response does NOT expose profile_completion
    // ══════════════════════════════════════════════════════════════════════════

    public function test_response_does_not_expose_profile_completion(): void
    {
        $me    = $this->discoverableUser();
        $other = $this->discoverableUser();

        $response = $this->actingAs($me, 'sanctum')->getJson($this->url);
        $item = collect($response->json('data.items'))->firstWhere('id', $other->id);

        $this->assertArrayNotHasKey('profile_completion', $item);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 29. Response includes expected public profile fields with correct values
    // ══════════════════════════════════════════════════════════════════════════

    public function test_response_includes_expected_public_profile_fields(): void
    {
        $me    = $this->discoverableUser();
        $other = $this->discoverableUser([
            'bio'          => 'I hike mountains.',
            'city'         => 'Shimla',
            'country'      => 'India',
            'travel_style' => 'adventure',
        ]);

        $response = $this->actingAs($me, 'sanctum')->getJson($this->url);
        $item = collect($response->json('data.items'))->firstWhere('id', $other->id);

        $this->assertSame($other->name, $item['name']);
        $this->assertSame('I hike mountains.', $item['bio']);
        $this->assertSame('Shimla', $item['city']);
        $this->assertSame('India', $item['country']);
        $this->assertSame('adventure', $item['travel_style']);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 30. Empty result returns valid empty pagination response
    // ══════════════════════════════════════════════════════════════════════════

    public function test_empty_result_returns_valid_pagination(): void
    {
        $me = $this->discoverableUser();

        $response = $this->actingAs($me, 'sanctum')->getJson($this->url);

        $response->assertStatus(200)
            ->assertJsonPath('data.pagination.total', 0)
            ->assertJsonCount(0, 'data.items');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 31. Multiple filters combine correctly (AND semantics)
    // ══════════════════════════════════════════════════════════════════════════

    public function test_multiple_filters_combine_correctly(): void
    {
        $me = $this->discoverableUser();

        // Matches both: adventurer with Manali destination
        $match = $this->discoverableUser(['travel_style' => 'adventure']);
        PreferredDestination::create([
            'user_id'     => $match->id,
            'destination' => 'Manali',
        ]);

        // Only style matches (no destination)
        $styleOnly = $this->discoverableUser(['travel_style' => 'adventure']);

        // Only destination matches (different style)
        $destOnly = $this->discoverableUser(['travel_style' => 'luxury']);
        PreferredDestination::create([
            'user_id'     => $destOnly->id,
            'destination' => 'Manali',
        ]);

        $response = $this->actingAs($me, 'sanctum')
            ->getJson($this->url . '?travel_style=adventure&destination=Manali');

        $ids = collect($response->json('data.items'))->pluck('id');
        $this->assertContains($match->id, $ids);
        $this->assertNotContains($styleOnly->id, $ids);
        $this->assertNotContains($destOnly->id, $ids);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 32. Deterministic sorting when profile_completion scores are equal
    // ══════════════════════════════════════════════════════════════════════════

    public function test_deterministic_sort_when_completion_scores_equal(): void
    {
        $me = $this->discoverableUser();

        // Both have same completion score (bio only = 15 each)
        $userA = User::factory()->create();
        $userA->profile()->create(['bio' => 'A', 'is_discoverable' => true]);

        $userB = User::factory()->create();
        $userB->profile()->create(['bio' => 'B', 'is_discoverable' => true]);

        $response = $this->actingAs($me, 'sanctum')->getJson($this->url);
        $ids = collect($response->json('data.items'))->pluck('id')->toArray();

        // Both should appear
        $this->assertContains($userA->id, $ids);
        $this->assertContains($userB->id, $ids);

        // Secondary sort is id DESC: higher id should appear first
        $posA = array_search($userA->id, $ids);
        $posB = array_search($userB->id, $ids);

        if ($userA->id > $userB->id) {
            $this->assertLessThan($posB, $posA);
        } else {
            $this->assertLessThan($posA, $posB);
        }
    }
}
