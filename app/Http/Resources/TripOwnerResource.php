<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lightweight owner representation in a Trip context.
 *
 * Deliberately minimal — only data the mobile client needs to render
 * a trip card owner summary. Avoids recursive serialization.
 */
class TripOwnerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'   => $this->id,
            'name' => $this->name,
        ];
    }
}
