<?php

namespace App\Http\Controllers;

use App\Http\Requests\Profile\UpdateInterestsRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Requests\Profile\UploadPhotoRequest;
use App\Http\Resources\InterestResource;
use App\Http\Resources\UserResource;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    use ApiResponse;

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
}
