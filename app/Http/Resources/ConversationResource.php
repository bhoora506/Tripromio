<?php

namespace App\Http\Resources;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API representation of a Conversation.
 *
 * Privacy rules:
 *   - email is NEVER exposed for either participant
 *   - password/tokens are NEVER exposed
 *   - Only safe public identity fields are shown for the other participant
 *
 * Expects the following to be eager-loaded on the Conversation model:
 *   - requester.profile
 *   - recipient.profile
 *   - latestMessage.sender.profile
 *
 * The authenticated user's ID is used to determine "other participant".
 */
class ConversationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Conversation $conversation */
        $conversation = $this->resource;

        $authUserId = $request->user()?->id;

        // Determine the other participant from the authenticated user's perspective.
        $other = $conversation->requester_id === $authUserId
            ? $conversation->recipient
            : $conversation->requester;

        $otherProfile = $other?->profile;

        // Latest message preview (safe: no sensitive user data).
        $latest = $conversation->latestMessage;

        // Unread count: messages from the OTHER participant that are unread.
        // Calculated via a COUNT query, not by loading all messages into PHP.
        $unreadCount = $conversation->messages()
            ->where('sender_id', '!=', $authUserId)
            ->whereNull('read_at')
            ->count();

        return [
            'id' => $this->id,

            'other_participant' => $other ? [
                'id'                => $other->id,
                'name'              => $other->name,
                'profile_photo_url' => $otherProfile?->profile_photo_url,
            ] : null,

            'latest_message' => $latest ? [
                'id'         => $latest->id,
                'body'       => $latest->body,
                'sender_id'  => $latest->sender_id,
                'created_at' => $latest->created_at,
            ] : null,

            'unread_count' => $unreadCount,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
