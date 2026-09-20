<?php

namespace App\Http\Controllers;

use App\Http\Requests\Profile\RegisterDeviceTokenRequest;
use App\Http\Requests\Profile\UnregisterDeviceTokenRequest;
use App\Http\Requests\Profile\UpdateInterestsRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Requests\Profile\UploadPhotoRequest;
use App\Http\Resources\InterestResource;
use App\Http\Resources\UserResource;
use App\Services\DeviceTokenService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly DeviceTokenService $deviceTokenService) {}

    /**
     * Get the authenticated user's profile and interests.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load(['profile', 'interests']);

        return $this->successResponse(
            data: ['user' => new UserResource($user)],
            message: 'Profile retrieved successfully'
        );
    }

    /**
     * Create or update the user's profile.
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        
        $profile = $user->profile()->updateOrCreate(
            ['user_id' => $user->id],
            $request->validated()
        );

        $user->load(['profile', 'interests']);

        return $this->successResponse(
            data: ['user' => new UserResource($user)],
            message: 'Profile updated successfully'
        );
    }

    /**
     * Update the user's selected interests.
     */
    public function updateInterests(UpdateInterestsRequest $request): JsonResponse
    {
        $user = $request->user();

        // Sync avoids duplicate entries and removes unselected ones
        $user->interests()->sync($request->validated('interest_ids'));

        $user->load(['profile', 'interests']);

        return $this->successResponse(
            data: [
                'user'      => new UserResource($user),
                'interests' => InterestResource::collection($user->interests),
            ],
            message: 'Interests updated successfully'
        );
    }

    /**
     * Upload a profile photo.
     */
    public function uploadPhoto(UploadPhotoRequest $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->profile()->firstOrCreate(['user_id' => $user->id]);

        // Delete old photo if exists
        if ($profile->profile_photo_path) {
            Storage::disk('public')->delete($profile->profile_photo_path);
        }

        $path = $request->file('photo')->store('profile-photos', 'public');

        $profile->update(['profile_photo_path' => $path]);

        $user->load(['profile', 'interests']);

        return $this->successResponse(
            data: ['user' => new UserResource($user)],
            message: 'Profile photo uploaded successfully'
        );
    }

    /**
     * Delete the current profile photo.
     */
    public function deletePhoto(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->profile;

        if ($profile && $profile->profile_photo_path) {
            Storage::disk('public')->delete($profile->profile_photo_path);
            $profile->update(['profile_photo_path' => null]);
        }

        $user->load(['profile', 'interests']);

        return $this->successResponse(
            data: ['user' => new UserResource($user)],
            message: 'Profile photo deleted successfully'
        );
    }

    // ── Device Token ──────────────────────────────────────────────────────────

    /**
     * Register an FCM device token for the authenticated user.
     *
     * The user_id is always derived from the authenticated Sanctum session
     * and never from the request body. The token is upserted so repeated
     * calls with the same token are idempotent.
     *
     * IMPORTANT: The fcm_token must NOT be returned in this response or any
     * other resource (UserResource, ProfileResource, etc.).
     */
    public function registerDeviceToken(RegisterDeviceTokenRequest $request): JsonResponse
    {
        $this->deviceTokenService->register(
            user: $request->user(),
            fcmToken: $request->validated('fcm_token'),
            platform: $request->validated('platform'),
        );

        return $this->successResponse(
            data: ['registered' => true],
            message: 'Device token registered successfully.',
        );
    }

    /**
     * Remove an FCM device token belonging to the authenticated user.
     *
     * Ownership is verified inside DeviceTokenService — only the token
     * owner's record is deleted. If the token does not exist or belongs to
     * a different user the response is still 200 (idempotent).
     */
    public function unregisterDeviceToken(UnregisterDeviceTokenRequest $request): JsonResponse
    {
        $this->deviceTokenService->unregister(
            user: $request->user(),
            fcmToken: $request->validated('fcm_token'),
        );

        return $this->successResponse(
            data: ['unregistered' => true],
            message: 'Device token removed successfully.',
        );
    }
}
