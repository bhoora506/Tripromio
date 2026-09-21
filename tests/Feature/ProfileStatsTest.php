<?php

namespace Tests\Feature;

use App\Enums\ConnectionStatus;
use App\Enums\MemberStatus;
use App\Models\ConnectionRequest;
use App\Models\Trip;
use App\Models\TripMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_correct_stats(): void
    {
        $user = User::factory()->create();

        // Created trip (1 active)
        $trip1 = Trip::factory()->create(['user_id' => $user->id]);
        TripMember::factory()->create([
            'trip_id' => $trip1->id,
            'user_id' => $user->id,
            'status' => MemberStatus::Active->value,
        ]);

        // Joined trip (1 active)
        $trip2 = Trip::factory()->create();
        TripMember::factory()->create([
            'trip_id' => $trip2->id,
            'user_id' => $user->id,
            'status' => MemberStatus::Active->value,
        ]);

        // Pending/Left trip (should not count)
        $trip3 = Trip::factory()->create();
        TripMember::factory()->create([
            'trip_id' => $trip3->id,
            'user_id' => $user->id,
            'status' => MemberStatus::Left->value,
        ]);

        // Connection Sent (1 accepted)
        $otherUser1 = User::factory()->create();
        ConnectionRequest::factory()->create([
            'requester_id' => $user->id,
            'recipient_id' => $otherUser1->id,
            'status' => ConnectionStatus::Accepted->value,
        ]);

        // Connection Received (1 accepted)
        $otherUser2 = User::factory()->create();
        ConnectionRequest::factory()->create([
            'requester_id' => $otherUser2->id,
            'recipient_id' => $user->id,
            'status' => ConnectionStatus::Accepted->value,
        ]);

        // Connection Pending (should not count)
        $otherUser3 = User::factory()->create();
        ConnectionRequest::factory()->create([
            'requester_id' => $user->id,
            'recipient_id' => $otherUser3->id,
            'status' => ConnectionStatus::Pending->value,
        ]);

        $response = $this->actingAs($user)->getJson('/api/profile/stats');
        
        $response->assertStatus(200)
                 ->assertJson([
                     'trips_count' => 2,
                     'connections_count' => 2,
                 ]);
    }

    public function test_requires_auth(): void
    {
        $response = $this->getJson('/api/profile/stats');
        $response->assertStatus(401);
    }
}
