<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API representation of a TripJoinRequest.
 *
 * Intentionally minimal to avoid over-exposing user data.
 * Includes requester's id and name only (mirrors TripOwnerResource pattern).
 *
 * Expects the following to be eager-loaded:
 *   - requester  (BelongsTo User)
 *   - trip       (BelongsTo Trip, optional — only included when loaded)
 */
class TripJoinRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'trip_id'    => $this->trip_id,
            'status'     => $this->status?->value,
            'requester'  => $this->whenLoaded('requester', fn () => [
                'id'   => $this->requester->id,
                'name' => $this->requester->name,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
