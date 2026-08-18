<?php

namespace App\Http\Controllers;

use App\Http\Resources\InterestResource;
use App\Models\Interest;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class InterestController extends Controller
{
    use ApiResponse;

    /**
     * List all available interests.
     */
    public function index(): JsonResponse
    {
        $interests = Interest::orderBy('name')->get();

        return $this->successResponse(
            data: ['interests' => InterestResource::collection($interests)],
            message: 'Interests retrieved successfully'
        );
    }
}
