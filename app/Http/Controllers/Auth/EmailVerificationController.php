<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    use ApiResponse;

    /**
     * Mark the user's email address as verified.
     *
     * Laravel's EmailVerificationRequest handles signature validation
     * and user resolution automatically.
     */
    public function verify(EmailVerificationRequest $request): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return $this->successResponse(
                data: null,
                message: 'Email address is already verified',
            );
        }

        $request->user()->markEmailAsVerified();

        event(new Verified($request->user()));

        return $this->successResponse(
            data: null,
            message: 'Email address verified successfully',
        );
    }

    /**
     * Resend the email verification notification.
     *
     * Rate-limited at the route level.
     */
    public function resend(Request $request): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return $this->successResponse(
                data: null,
                message: 'Email address is already verified',
            );
        }

        $request->user()->sendEmailVerificationNotification();

        return $this->successResponse(
            data: null,
            message: 'Verification email has been resent',
        );
    }
}
