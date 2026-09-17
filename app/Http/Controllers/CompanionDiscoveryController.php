<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompanionDiscoveryRequest;
use App\Http\Resources\CompanionResource;
use App\Services\CompanionDiscoveryService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Companion Discovery Controller.
 *
 * Thin controller — all query/business logic lives in CompanionDiscoveryService.
 * Mirrors TripController conventions.
 */
class CompanionDiscoveryController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly CompanionDiscoveryService $discoveryService,
    ) {
    }

    /**
     * Companion discovery feed — paginated list of discoverable users.
     * GET /api/companions
     */
    public function index(CompanionDiscoveryRequest $request): JsonResponse
    {
        $companions = $this->discoveryService->discover(
            authenticatedUserId: $request->user()->id,
            filters:             $request->validated(),
        );

        return $this->successResponse(
            data: [
                'items'      => CompanionResource::collection($companions->items()),
                'pagination' => [
                    'total'        => $companions->total(),
                    'per_page'     => $companions->perPage(),
                    'current_page' => $companions->currentPage(),
                    'last_page'    => $companions->lastPage(),
                    'has_more'     => $companions->hasMorePages(),
                ],
            ],
            message: 'Companions retrieved successfully.',
        );
    }
}
