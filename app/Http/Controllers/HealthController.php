<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    use ApiResponse;

    /**
     * Return the API health status.
     *
     * Dependency-free endpoint used to verify the API pipeline is operational.
     */
    public function __invoke(): JsonResponse
    {
        return $this->successResponse(
            data: ['status' => 'ok'],
            message: 'Tripromio API is running',
        );
    }
}
