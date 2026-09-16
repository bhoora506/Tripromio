<?php

namespace App\Http\Controllers;

use App\Http\Resources\ConnectionRequestResource;
use App\Models\ConnectionRequest;
use App\Models\User;
use App\Services\ConnectionRequestService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConnectionRequestController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ConnectionRequestService $service,
    ) {
    }

    /**
     * Send a connection request to another user.
     * POST /api/connections
     *
     * Body: { "recipient_id": int }
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'recipient_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $recipient = User::findOrFail($validated['recipient_id']);

        $connectionRequest = $this->service->sendRequest($request->user(), $recipient);
        $connectionRequest->load('requester.profile', 'recipient.profile');

        return $this->successResponse(
            data:    ['connection_request' => new ConnectionRequestResource($connectionRequest)],
            message: 'Connection request sent successfully.',
            status:  201,
        );
    }

    /**
     * List connection requests received by the authenticated user.
     * GET /api/connections/received
     */
    public function received(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);
        $perPage = (int) ($validated['per_page'] ?? 20);

        $requests = ConnectionRequest::where('recipient_id', $request->user()->id)
            ->with('requester.profile')
            ->latest()
            ->paginate($perPage);

        return $this->successResponse(
            data: [
                'items'      => ConnectionRequestResource::collection($requests->items()),
                'pagination' => [
                    'total'        => $requests->total(),
                    'per_page'     => $requests->perPage(),
                    'current_page' => $requests->currentPage(),
                    'last_page'    => $requests->lastPage(),
                    'has_more'     => $requests->hasMorePages(),
                ],
            ],
            message: 'Received connection requests retrieved successfully.',
        );
    }

    /**
     * List connection requests sent by the authenticated user.
     * GET /api/connections/sent
     */
    public function sent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);
        $perPage = (int) ($validated['per_page'] ?? 20);

        $requests = ConnectionRequest::where('requester_id', $request->user()->id)
            ->with('recipient.profile')
            ->latest()
            ->paginate($perPage);

        return $this->successResponse(
            data: [
                'items'      => ConnectionRequestResource::collection($requests->items()),
                'pagination' => [
                    'total'        => $requests->total(),
                    'per_page'     => $requests->perPage(),
                    'current_page' => $requests->currentPage(),
                    'last_page'    => $requests->lastPage(),
                    'has_more'     => $requests->hasMorePages(),
                ],
            ],
            message: 'Sent connection requests retrieved successfully.',
        );
    }

    /**
     * Accept a pending connection request (recipient only).
     * POST /api/connections/{connectionRequest}/accept
     */
    public function accept(Request $request, ConnectionRequest $connectionRequest): JsonResponse
    {
        $this->authorize('accept', $connectionRequest);

        $connectionRequest = $this->service->accept($connectionRequest);
        $connectionRequest->load('requester.profile', 'recipient.profile');

        return $this->successResponse(
            data:    ['connection_request' => new ConnectionRequestResource($connectionRequest)],
            message: 'Connection request accepted.',
        );
    }

    /**
     * Reject a pending connection request (recipient only).
     * POST /api/connections/{connectionRequest}/reject
     */
    public function reject(Request $request, ConnectionRequest $connectionRequest): JsonResponse
    {
        $this->authorize('reject', $connectionRequest);

        $connectionRequest = $this->service->reject($connectionRequest);
        $connectionRequest->load('requester.profile', 'recipient.profile');

        return $this->successResponse(
            data:    ['connection_request' => new ConnectionRequestResource($connectionRequest)],
            message: 'Connection request rejected.',
        );
    }

    /**
     * Cancel a pending connection request (requester only).
     * POST /api/connections/{connectionRequest}/cancel
     */
    public function cancel(Request $request, ConnectionRequest $connectionRequest): JsonResponse
    {
        $this->authorize('cancel', $connectionRequest);

        $connectionRequest = $this->service->cancel($connectionRequest);
        $connectionRequest->load('requester.profile', 'recipient.profile');

        return $this->successResponse(
            data:    ['connection_request' => new ConnectionRequestResource($connectionRequest)],
            message: 'Connection request cancelled.',
        );
    }
}
