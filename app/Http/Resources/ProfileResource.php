<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'bio'                   => $this->bio,
            'city'                  => $this->city,
            'country'               => $this->country,
            'languages'             => $this->languages ?? [],
            'travel_style'          => $this->travel_style?->value,
            'profile_photo_url'     => $this->profile_photo_url,
            'preferred_budget_min'  => $this->preferred_budget_min,
            'preferred_budget_max'  => $this->preferred_budget_max,
        ];
    }
}
