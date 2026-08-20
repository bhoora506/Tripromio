<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API representation of a Trip.
 *
 * Expects the following to be eager-loaded on the model:
 *   - owner          (BelongsTo User)
 *   - active_members_count (withCount)
 *
 * Pagination-friendly: does NOT re-run per-trip queries when used
 * in TripResource::collection() — counts are loaded via withCount().
 */
class TripResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Prefer pre-loaded count over re-running the query
        $activeMemberCount = $this->active_members_count
            ?? $this->activeMembers()->count();

        $remainingSlots = max(0, $this->max_members - $activeMemberCount);

        return [
            'id'              => $this->id,
            'title'           => $this->title,
            'destination'     => $this->destination,
            'place_id'        => $this->place_id,
            'latitude'        => $this->latitude,
            'longitude'       => $this->longitude,
            'start_date'      => $this->start_date?->toDateString(),
            'end_date'        => $this->end_date?->toDateString(),
            'budget_min'      => $this->budget_min,
            'budget_max'      => $this->budget_max,
            'trip_type'       => $this->trip_type?->value,
            'description'     => $this->description,
            'max_members'     => $this->max_members,
            'status'          => $this->status?->value,
            'owner'           => new TripOwnerResource($this->whenLoaded('owner')),
            'interests'       => InterestResource::collection($this->whenLoaded('interests')),
            'member_count'    => $activeMemberCount,
            'remaining_slots' => $remainingSlots,
            'created_at'      => $this->created_at,
            'updated_at'      => $this->updated_at,
        ];
    }
}
