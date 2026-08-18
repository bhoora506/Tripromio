<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    /**
     * Return a standardised success JSON response.
     *
     * @param  mixed  $data
     * @param  string  $message
     * @param  int  $status
     */
    protected function successResponse(mixed $data = [], string $message = 'Operation successful', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $status);
    }

    /**
     * Return a standardised error JSON response.
     *
     * @param  string  $message
     * @param  array<string, mixed>  $errors
     * @param  int  $status
     */
    protected function errorResponse(string $message = 'An error occurred', array $errors = [], int $status = 400): JsonResponse
    {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if (! empty($errors)) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }
}
