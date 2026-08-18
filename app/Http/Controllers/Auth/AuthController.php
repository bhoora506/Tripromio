<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class AuthController extends Controller
{
    use ApiResponse;

    /**
     * Register a new user account.
     *
     * Issues a Sanctum token immediately after registration.
     * Dispatches the Registered event so the email verification
     * notification is sent automatically.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => $request->password, // hashed by model cast
        ]);

        event(new Registered($user));

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->successResponse(
            data: [
                'user'  => new UserResource($user),
                'token' => $token,
            ],
            message: 'Registration successful',
            status: 201,
        );
    }

    /**
     * Authenticate an existing user and issue a Sanctum token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        if (! Auth::attempt($request->only('email', 'password'))) {
            return $this->errorResponse(
                message: 'Invalid credentials',
                status: 401,
            );
        }

        /** @var User $user */
        $user  = Auth::user();
        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->successResponse(
            data: [
                'user'  => new UserResource($user),
                'token' => $token,
            ],
            message: 'Login successful',
        );
    }

    /**
     * Return the currently authenticated user.
     */
    public function me(Request $request): JsonResponse
    {
        return $this->successResponse(
            data: ['user' => new UserResource($request->user())],
            message: 'Authenticated user',
        );
    }

    /**
     * Revoke the current Sanctum token and log the user out.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->successResponse(
            data: null,
            message: 'Logout successful',
        );
    }

    /**
     * Send a password reset link to the given email address.
     *
     * The response is intentionally generic to prevent user enumeration.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        // Laravel's Password broker handles the lookup, token generation,
        // and email dispatch. We do not inspect the result to avoid
        // revealing whether an account exists for the given email.
        Password::sendResetLink($request->only('email'));

        return $this->successResponse(
            data: null,
            message: 'If an account exists for this email, password reset instructions have been sent.',
        );
    }

    /**
     * Reset the user's password using the broker-issued token.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill(['password' => Hash::make($password)])->save();
            }
        );

        if ($status !== Password::PasswordReset) {
            return $this->errorResponse(
                message: 'Invalid or expired password reset token',
                status: 422,
            );
        }

        return $this->successResponse(
            data: null,
            message: 'Password has been reset successfully',
        );
    }
}
