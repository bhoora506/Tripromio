<?php

namespace App\Http\Controllers;

use App\Http\Resources\TripMemberResource;
use App\Models\Trip;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TripMemberController extends Controller
{
    use ApiResponse;

    /**
     * List active members of a trip.
     * GET /api/trips/{trip}/members
     */
    public function index(Request $request, Trip $trip): JsonResponse
    {
        $this->authorize('view', $trip);

        $members = $trip->activeMembers()
            ->with('user')
            ->get();

        return $this->successResponse(
            data:    ['members' => TripMemberResource::collection($members)],
            message: 'Trip members retrieved successfully.',
        );
    }
}
