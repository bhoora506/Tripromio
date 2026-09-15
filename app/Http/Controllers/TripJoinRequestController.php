<?php

namespace App\Http\Controllers;

use App\Http\Resources\TripJoinRequestResource;
use App\Models\Trip;
use App\Models\TripJoinRequest;
use App\Services\TripJoinRequestService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TripJoinRequestController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly TripJoinRequestService $service,
    ) {
    }

    /**
     * Submit a join request for a trip.
     * POST /api/trips/{trip}/join-requests
     */
    public function store(Request $request, Trip $trip): JsonResponse
    {
        $this->authorize('store', [TripJoinRequest::class, $trip]);

        $joinRequest = $this->service->createRequest($request->user(), $trip);
        $joinRequest->load('requester');

        return $this->successResponse(
            data:    ['join_request' => new TripJoinRequestResource($joinRequest)],
            message: 'Join request submitted successfully.',
            status:  201,
        );
    }

    /**
     * List pending join requests for a trip (trip owner only).
     * GET /api/trips/{trip}/join-requests
     */
    public function index(Request $request, Trip $trip): JsonResponse
    {
        $this->authorize('index', [TripJoinRequest::class, $trip]);

        $joinRequests = $trip->joinRequests()
            ->with('requester')
            ->latest()
            ->get();

        return $this->successResponse(
            data:    ['join_requests' => TripJoinRequestResource::collection($joinRequests)],
            message: 'Join requests retrieved successfully.',
        );
    }

    /**
     * Approve a pending join request (trip owner only).
     * POST /api/trips/{trip}/join-requests/{joinRequest}/approve
     */
    public function approve(Request $request, Trip $trip, TripJoinRequest $joinRequest): JsonResponse
    {
        $this->ensureRequestBelongsToTrip($joinRequest, $trip);
        $this->authorize('approve', $joinRequest);

        $joinRequest = $this->service->approve($joinRequest);
        $joinRequest->load('requester');

        return $this->successResponse(
            data:    ['join_request' => new TripJoinRequestResource($joinRequest)],
            message: 'Join request approved.',
        );
    }

    /**
     * Reject a pending join request (trip owner only).
     * POST /api/trips/{trip}/join-requests/{joinRequest}/reject
     */
    public function reject(Request $request, Trip $trip, TripJoinRequest $joinRequest): JsonResponse
    {
        $this->ensureRequestBelongsToTrip($joinRequest, $trip);
        $this->authorize('reject', $joinRequest);

        $joinRequest = $this->service->reject($joinRequest);
        $joinRequest->load('requester');

        return $this->successResponse(
            data:    ['join_request' => new TripJoinRequestResource($joinRequest)],
            message: 'Join request rejected.',
        );
    }

    /**
     * Cancel a pending join request (requester only).
     * POST /api/trips/{trip}/join-requests/{joinRequest}/cancel
     */
    public function cancel(Request $request, Trip $trip, TripJoinRequest $joinRequest): JsonResponse
    {
        $this->ensureRequestBelongsToTrip($joinRequest, $trip);
        $this->authorize('cancel', $joinRequest);

        $joinRequest = $this->service->cancel($joinRequest);
        $joinRequest->load('requester');

        return $this->successResponse(
            data:    ['join_request' => new TripJoinRequestResource($joinRequest)],
            message: 'Join request cancelled.',
        );
    }

    /**
     * Verify that the join request belongs to the given trip.
     * Prevents a request for trip A from being actioned under trip B's URL.
     */
    private function ensureRequestBelongsToTrip(TripJoinRequest $joinRequest, Trip $trip): void
    {
        if ($joinRequest->trip_id !== $trip->id) {
            abort(404, 'Join request not found for this trip.');
        }
    }
}
