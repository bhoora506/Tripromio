<?php

namespace App\Http\Controllers;

use App\Models\PreferredDestination;
use App\Http\Requests\Profile\SavePreferredDestinationRequest;
use App\Http\Resources\PreferredDestinationResource;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PreferredDestinationController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $destinations = $request->user()->preferredDestinations()->latest()->get();

        return $this->successResponse(
            data: ['destinations' => PreferredDestinationResource::collection($destinations)],
            message: 'Preferred destinations retrieved successfully'
        );
    }

    public function store(SavePreferredDestinationRequest $request): JsonResponse
    {
        $destination = $request->user()->preferredDestinations()->create($request->validated());

        return $this->successResponse(
            data: ['destination' => new PreferredDestinationResource($destination)],
            message: 'Preferred destination added successfully',
            status: 201
        );
    }

    public function update(SavePreferredDestinationRequest $request, PreferredDestination $destination): JsonResponse
    {
        abort_if($destination->user_id !== $request->user()->id, 403, 'Unauthorized action.');

        $destination->update($request->validated());

        return $this->successResponse(
            data: ['destination' => new PreferredDestinationResource($destination)],
            message: 'Preferred destination updated successfully'
        );
    }

    public function destroy(Request $request, PreferredDestination $destination): JsonResponse
    {
        abort_if($destination->user_id !== $request->user()->id, 403, 'Unauthorized action.');

        $destination->delete();

        return $this->successResponse(
            message: 'Preferred destination deleted successfully'
        );
    }
}
