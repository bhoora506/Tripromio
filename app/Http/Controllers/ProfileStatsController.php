<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Enums\ConnectionStatus;
use App\Enums\MemberStatus;
use Illuminate\Support\Facades\DB;

class ProfileStatsController extends Controller
{
    /**
     * Get aggregated statistics for the authenticated user.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        // Count unique active trips (owner or joined)
        $tripsCount = $user->tripMembers()
            ->where('status', MemberStatus::Active->value)
            ->count();

        // Count unique accepted connections
        // Note: A connection is represented by a single row where the user is either requester or recipient.
        $connectionsCount = DB::table('connection_requests')
            ->where('status', ConnectionStatus::Accepted->value)
            ->where(function ($query) use ($user) {
                $query->where('requester_id', $user->id)
                      ->orWhere('recipient_id', $user->id);
            })
            ->count();

        return response()->json([
            'trips_count'       => $tripsCount,
            'connections_count' => $connectionsCount,
        ]);
    }
}
