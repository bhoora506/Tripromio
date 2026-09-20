<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Support\Facades\DB;

/**
 * Centralises all business rules for FCM device-token lifecycle.
 *
 * Design decisions:
 *
 * Upsert strategy — unique on fcm_token:
 *   A single FCM token identifies a specific app installation on a device.
 *   If the same physical device is used by a different account (e.g. after
 *   logout / account switch), the token must be reassigned to the new
 *   authenticated user rather than creating a duplicate row.
 *   This is achieved by looking up the record by token, then updating its
 *   user_id to the current authenticated user.
 *
 * Idempotence:
 *   Calling register() multiple times with the same token and user returns
 *   success each time without creating duplicates. last_used_at is always
 *   refreshed so stale-token cleanup can be implemented in a later phase.
 *
 * No DB transaction needed for register():
 *   The updateOrCreate is a single SQL statement equivalent and there is no
 *   multi-step operation requiring atomicity. A transaction is not added
 *   to avoid over-engineering, matching the project convention (see
 *   TravelAvailabilityController, PreferredDestinationController).
 *
 * Authorization:
 *   user_id is always derived from the authenticated Sanctum session in the
 *   controller — never from the request body. The service receives the
 *   resolved $user object. unregister() enforces ownership with a WHERE
 *   user_id = $user->id clause.
 */
class DeviceTokenService
{
    /**
     * Register (or update) an FCM device token for the authenticated user.
     *
     * Logic:
     *   1. Look for an existing UserDevice row by fcm_token.
     *   2. If found: update user_id (reassign), platform, and last_used_at.
     *   3. If not found: create a new row.
     *
     * This is idempotent — repeated calls with the same arguments produce
     * the same result without creating duplicate rows.
     */
    public function register(User $user, string $fcmToken, string $platform): UserDevice
    {
        $existing = UserDevice::where('fcm_token', $fcmToken)->first();

        if ($existing !== null) {
            $existing->user_id     = $user->id;
            $existing->platform    = $platform;
            $existing->last_used_at = now();
            $existing->save();

            return $existing;
        }

        return UserDevice::create([
            'user_id'      => $user->id,
            'fcm_token'    => $fcmToken,
            'platform'     => $platform,
            'last_used_at' => now(),
        ]);
    }

    /**
     * Remove a device token, verifying it belongs to the authenticated user.
     *
     * Ownership is verified by the WHERE user_id = $user->id clause — a user
     * cannot remove another user's token. If the token does not exist or
     * belongs to a different user, the method silently returns true so that
     * the operation is safe to call during logout even if the token was
     * already cleaned up.
     *
     * @return bool  Always true (idempotent).
     */
    public function unregister(User $user, string $fcmToken): bool
    {
        UserDevice::where('fcm_token', $fcmToken)
            ->where('user_id', $user->id)
            ->delete();

        return true;
    }
}
