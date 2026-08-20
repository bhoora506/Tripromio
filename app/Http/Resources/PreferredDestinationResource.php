<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PreferredDestinationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'destination' => $this->destination,
            'place_id'    => $this->place_id,
            'latitude'    => $this->latitude,
            'longitude'   => $this->longitude,
            'created_at'  => $this->created_at,
        ];
    }
}
