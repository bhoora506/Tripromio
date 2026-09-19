<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API representation of a Message.
 *
 * Privacy rules:
 *   - email is NEVER exposed for sender
 *   - password/tokens are NEVER exposed
 *   - only id, name, profile_photo_url are shown for the sender
 *
 * Expects the following to be eager-loaded:
 *   - sender.profile
 */
class MessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $sender = $this->whenLoaded('sender');
        $senderProfile = $sender?->profile ?? null;

        return [
            'id'              => $this->id,
            'conversation_id' => $this->conversation_id,

            'sender' => $this->whenLoaded('sender', fn () => [
                'id'                => $this->sender->id,
                'name'              => $this->sender->name,
                'profile_photo_url' => $senderProfile?->profile_photo_url,
            ]),

            'body'       => $this->body,
            'read_at'    => $this->read_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
