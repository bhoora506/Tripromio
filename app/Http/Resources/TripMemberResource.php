<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TripMemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->id,
            'user'      => [
                'id'   => $this->user->id,
                'name' => $this->user->name,
            ],
            'role'      => $this->role?->value,
            'status'    => $this->status?->value,
            'joined_at' => $this->joined_at,
        ];
    }
}
