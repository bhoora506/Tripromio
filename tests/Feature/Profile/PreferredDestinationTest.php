<?php

namespace Tests\Feature\Profile;

use App\Models\PreferredDestination;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PreferredDestinationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_unauthenticated_users_cannot_access_destinations()
    {
        $this->getJson('/api/profile/destinations')->assertUnauthorized();
        $this->postJson('/api/profile/destinations', [])->assertUnauthorized();
        $this->putJson('/api/profile/destinations/1', [])->assertUnauthorized();
        $this->deleteJson('/api/profile/destinations/1')->assertUnauthorized();
    }

    public function test_user_can_list_their_destinations()
    {
        PreferredDestination::create(['user_id' => $this->user->id, 'destination' => 'Udaipur']);
        $otherUser = User::factory()->create();
        PreferredDestination::create(['user_id' => $otherUser->id, 'destination' => 'Jaipur']);

        $response = $this->actingAs($this->user)->getJson('/api/profile/destinations');

        $response->assertOk()
            ->assertJsonCount(1, 'data.destinations')
            ->assertJsonPath('data.destinations.0.destination', 'Udaipur')
            ->assertJsonMissing(['destination' => 'Jaipur']);
    }

    public function test_user_can_add_preferred_destination()
    {
        $payload = [
            'destination' => 'Udaipur',
            'place_id'    => 'ChIJc1-pL4_aZTkRlT2Z5K-V554',
            'latitude'    => 24.5854,
            'longitude'   => 73.7125,
        ];

        $response = $this->actingAs($this->user)->postJson('/api/profile/destinations', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.destination.destination', 'Udaipur')
            ->assertJsonPath('data.destination.place_id', 'ChIJc1-pL4_aZTkRlT2Z5K-V554');

        $this->assertDatabaseHas('preferred_destinations', [
            'user_id'     => $this->user->id,
            'destination' => 'Udaipur',
            'place_id'    => 'ChIJc1-pL4_aZTkRlT2Z5K-V554',
        ]);
    }

    public function test_cannot_exceed_max_destinations_limit()
    {
        // Add 50 destinations
        for ($i = 1; $i <= 50; $i++) {
            PreferredDestination::create([
                'user_id'     => $this->user->id,
                'destination' => "Dest {$i}",
            ]);
        }

        $response = $this->actingAs($this->user)->postJson('/api/profile/destinations', [
            'destination' => 'Udaipur',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['destination']);
            
        $this->assertEquals(50, $this->user->preferredDestinations()->count());
    }

    public function test_case_a_duplicate_detection_same_place_id()
    {
        PreferredDestination::create([
            'user_id'     => $this->user->id,
            'destination' => 'Old Name',
            'place_id'    => 'place_123',
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/profile/destinations', [
            'destination' => 'New Name',
            'place_id'    => 'place_123',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['destination']);
    }

    public function test_case_b_duplicate_detection_string_match_no_place_id()
    {
        PreferredDestination::create([
            'user_id'     => $this->user->id,
            'destination' => 'Udaipur',
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/profile/destinations', [
            'destination' => ' udaipur ',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['destination']);
    }

    public function test_case_c_duplicate_detection_mixed_place_id()
    {
        PreferredDestination::create([
            'user_id'     => $this->user->id,
            'destination' => 'udaipur',
        ]);

        // Attempting to add with place_id but same string
        $response = $this->actingAs($this->user)->postJson('/api/profile/destinations', [
            'destination' => 'Udaipur',
            'place_id'    => 'place_456'
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['destination']);
    }

    public function test_case_d_update_allows_keeping_same_data()
    {
        $dest = PreferredDestination::create([
            'user_id'     => $this->user->id,
            'destination' => 'Udaipur',
            'place_id'    => 'place_123',
        ]);

        $response = $this->actingAs($this->user)->putJson("/api/profile/destinations/{$dest->id}", [
            'destination' => 'Udaipur',
            'place_id'    => 'place_123',
            'latitude'    => 24.5854,
        ]);

        $response->assertOk();
        $this->assertEquals(24.5854, $dest->fresh()->latitude);
    }

    public function test_cannot_update_others_destination()
    {
        $otherUser = User::factory()->create();
        $dest = PreferredDestination::create([
            'user_id'     => $otherUser->id,
            'destination' => 'Jaipur',
        ]);

        $this->actingAs($this->user)->putJson("/api/profile/destinations/{$dest->id}", [
            'destination' => 'Jaipur 2'
        ])->assertForbidden();
    }

    public function test_user_can_delete_destination()
    {
        $dest = PreferredDestination::create([
            'user_id'     => $this->user->id,
            'destination' => 'Udaipur',
        ]);

        $this->actingAs($this->user)->deleteJson("/api/profile/destinations/{$dest->id}")
            ->assertOk();

        $this->assertDatabaseMissing('preferred_destinations', ['id' => $dest->id]);
    }

    public function test_cannot_delete_others_destination()
    {
        $otherUser = User::factory()->create();
        $dest = PreferredDestination::create([
            'user_id'     => $otherUser->id,
            'destination' => 'Jaipur',
        ]);

        $this->actingAs($this->user)->deleteJson("/api/profile/destinations/{$dest->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('preferred_destinations', ['id' => $dest->id]);
    }
}
