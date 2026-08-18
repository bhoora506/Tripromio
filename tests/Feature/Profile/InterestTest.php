<?php

namespace Tests\Feature\Profile;

use App\Models\Interest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InterestTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_all_interests(): void
    {
        Interest::create(['name' => 'Trekking', 'slug' => 'trekking']);
        Interest::create(['name' => 'Food', 'slug' => 'food']);

        $response = $this->getJson('/api/interests');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data.interests')
            ->assertJsonPath('data.interests.0.name', 'Food') // Should be alphabetical
            ->assertJsonPath('data.interests.1.name', 'Trekking');
    }

    public function test_authenticated_user_can_update_interests(): void
    {
        $user = User::factory()->create();
        $interest = Interest::create(['name' => 'Trekking', 'slug' => 'trekking']);

        $response = $this->actingAs($user, 'sanctum')->putJson('/api/profile/interests', [
            'interest_ids' => [$interest->id],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.interests.0.id', $interest->id);

        $this->assertDatabaseHas('user_interests', [
            'user_id' => $user->id,
            'interest_id' => $interest->id,
        ]);
    }

    public function test_invalid_interest_id_is_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->putJson('/api/profile/interests', [
            'interest_ids' => [999], // Does not exist
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['interest_ids.0']);
    }

    public function test_duplicate_interest_ids_are_handled_gracefully(): void
    {
        $user = User::factory()->create();
        $interest = Interest::create(['name' => 'Trekking', 'slug' => 'trekking']);

        $response = $this->actingAs($user, 'sanctum')->putJson('/api/profile/interests', [
            'interest_ids' => [$interest->id, $interest->id],
        ]);

        $response->assertStatus(200);
        $this->assertCount(1, $user->fresh()->interests);
    }

    public function test_unauthenticated_user_cannot_update_interests(): void
    {
        $response = $this->putJson('/api/profile/interests', [
            'interest_ids' => [1],
        ]);

        $response->assertStatus(401);
    }
}
