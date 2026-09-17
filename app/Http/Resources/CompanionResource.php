<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Discovery-safe companion profile representation.
 *
 * Privacy rules:
 *   - email is NEVER exposed
 *   - email_verified_at is NEVER exposed
 *   - password/tokens are NEVER exposed
 *   - preferred_budget_min/max are NOT exposed (private financial preference)
 *   - profile_completion score is NOT exposed (internal metric)
 *   - travel availability windows are NOT included (not a product requirement for MVP feed)
 *   - is_discoverable flag is NOT exposed (server-side filter only)
 *
 * Expects the following to be eager-loaded on the User model:
 *   - profile           (HasOne  → UserProfile)
 *   - interests         (BelongsToMany → Interest)
 *   - preferredDestinations (HasMany → PreferredDestination)
 */
class CompanionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var \App\Models\UserProfile|null $profile */
        $profile = $this->profile;

        return [
            'id'   => $this->id,
            'name' => $this->name,

            // Profile fields (all from UserProfile)
            'profile_photo_url' => $profile?->profile_photo_url,
            'bio'               => $profile?->bio,
            'city'              => $profile?->city,
            'country'           => $profile?->country,
            'languages'         => $profile?->languages ?? [],
            'travel_style'      => $profile?->travel_style?->value,

            // Many-to-many interests
            'interests' => $this->whenLoaded('interests', fn () =>
                $this->interests->map(fn ($interest) => [
                    'id'   => $interest->id,
                    'name' => $interest->name,
                    'slug' => $interest->slug,
                ])->values()
            ),

            // Preferred destinations (name + place_id only — no lat/lng in discovery)
            'preferred_destinations' => $this->whenLoaded('preferredDestinations', fn () =>
                $this->preferredDestinations->map(fn ($dest) => [
                    'id'          => $dest->id,
                    'destination' => $dest->destination,
                    'place_id'    => $dest->place_id,
                ])->values()
            ),
        ];
    }
}
