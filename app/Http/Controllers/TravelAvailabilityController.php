<?php

namespace App\Http\Controllers;

use App\Http\Requests\Profile\CreateAvailabilityRequest;
use App\Http\Requests\Profile\UpdateAvailabilityRequest;
use App\Http\Resources\TravelAvailabilityResource;
use App\Models\TravelAvailability;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TravelAvailabilityController extends Controller
{
    use ApiResponse;

    /**
     * List the authenticated user's availability windows.
     * GET /api/profile/availability
     */
    public function index(Request $request): JsonResponse
    {
        $availabilities = $request->user()
            ->travelAvailabilities()
            ->orderBy('start_date')
            ->get();

        return $this->successResponse(
            data:    ['availabilities' => TravelAvailabilityResource::collection($availabilities)],
            message: 'Availability retrieved successfully.',
        );
    }

    /**
     * Create a new availability window.
     * POST /api/profile/availability
     */
    public function store(CreateAvailabilityRequest $request): JsonResponse
    {
        $availability = $request->user()->travelAvailabilities()->create(
            $request->validated()
        );

        return $this->successResponse(
            data:    ['availability' => new TravelAvailabilityResource($availability)],
            message: 'Availability created successfully.',
            status:  201,
        );
    }

    /**
     * Update an existing availability window (owner only).
     * PUT /api/profile/availability/{availability}
     */
    public function update(UpdateAvailabilityRequest $request, TravelAvailability $availability): JsonResponse
    {
        // Ensure the authenticated user owns this availability record
        if ($availability->user_id !== $request->user()->id) {
            abort(403);
        }

        $availability->update($request->validated());

        return $this->successResponse(
            data:    ['availability' => new TravelAvailabilityResource($availability->fresh())],
            message: 'Availability updated successfully.',
        );
    }

    /**
     * Delete an availability window (owner only).
     * DELETE /api/profile/availability/{availability}
     */
    public function destroy(Request $request, TravelAvailability $availability): JsonResponse
    {
        if ($availability->user_id !== $request->user()->id) {
            abort(403);
        }

        $availability->delete();

        return $this->successResponse(
            data:    [],
            message: 'Availability deleted successfully.',
        );
    }
}
