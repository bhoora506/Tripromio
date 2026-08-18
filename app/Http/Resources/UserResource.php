<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serialises the User model for API responses.
 *
 * Only exposes fields appropriate for the mobile client.
 * Never exposes password, remember_token, or internal system fields.
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'name'                 => $this->name,
            'email'                => $this->email,
            'email_verified_at'    => $this->email_verified_at,
            'profile'              => new ProfileResource($this->whenLoaded('profile')),
            'interests'            => InterestResource::collection($this->whenLoaded('interests')),
            'profile_completion'   => app(\App\Services\ProfileCompletionService::class)->calculate($this->resource),
            'created_at'           => $this->created_at,
        ];
    }
}
