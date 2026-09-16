<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API representation of a ConnectionRequest.
 *
 * Privacy rules enforced here:
 *   - email is NEVER exposed (for either requester or recipient)
 *   - only id, name, and profile_photo_url are shown per user
 *
 * Expects the following to be eager-loaded:
 *   - requester  (BelongsTo User → profile)
 *   - recipient  (BelongsTo User → profile)
 */
class ConnectionRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->id,
            'status'    => $this->status?->value,
            'requester' => $this->whenLoaded('requester', fn () => [
                'id'                => $this->requester->id,
                'name'              => $this->requester->name,
                'profile_photo_url' => $this->requester->profile?->profile_photo_url,
            ]),
            'recipient' => $this->whenLoaded('recipient', fn () => [
                'id'                => $this->recipient->id,
                'name'              => $this->recipient->name,
                'profile_photo_url' => $this->recipient->profile?->profile_photo_url,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
