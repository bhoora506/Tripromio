<?php

namespace App\Services;

use App\Models\User;

class ProfileCompletionService
{
    /**
     * Calculate the profile completion percentage for a given user.
     * Weights: Photo (20%), Bio (15%), City (15%), Country (10%), Languages (15%), Travel Style (10%), Interests (15%)
     */
    public function calculate(User $user): int
    {
        $completion = 0;
        
        // Ensure user has a profile record initialized, but we don't have to save it here
        $profile = $user->profile;

        if ($profile) {
            if ($profile->profile_photo_path) {
                $completion += 20;
            }
            if (!empty($profile->bio)) {
                $completion += 15;
            }
            if (!empty($profile->city)) {
                $completion += 15;
            }
            if (!empty($profile->country)) {
                $completion += 10;
            }
            if (!empty($profile->languages) && is_array($profile->languages) && count($profile->languages) > 0) {
                $completion += 15;
            }
            if (!empty($profile->travel_style)) {
                $completion += 10;
            }
        }

        // Check interests
        if ($user->interests()->count() > 0) {
            $completion += 15;
        }

        return min(100, $completion);
    }
}
