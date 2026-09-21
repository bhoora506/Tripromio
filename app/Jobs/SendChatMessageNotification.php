<?php

namespace App\Jobs;

use App\Models\Message;
use App\Services\FCMService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendChatMessageNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Message $message
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(FCMService $fcmService): void
    {
        try {
            // Eager load the conversation and recipient
            $this->message->loadMissing(['conversation.requester', 'conversation.recipient', 'sender.profile']);
            $conversation = $this->message->conversation;
            $sender = $this->message->sender;

            // Determine recipient
            $recipient = $conversation->otherParticipant($sender);

            // Fetch recipient's active device tokens
            $tokens = $recipient->userDevices()->pluck('fcm_token');

            if ($tokens->isEmpty()) {
                return; // Nothing to do
            }

            // Notification payload
            $senderName = $sender->profile->display_name ?? $sender->name;
            $title = $senderName;
            
            // Truncate the message body for a safe, short preview
            $bodyPreview = mb_substr($this->message->body, 0, 100);
            if (mb_strlen($this->message->body) > 100) {
                $bodyPreview .= '...';
            }

            $data = [
                'type' => 'chat_message',
                'conversation_id' => (string) $conversation->id,
            ];

            // Send to all devices
            foreach ($tokens as $token) {
                // If it fails, the service handles cleanup/logging, 
                // and we continue to the next device.
                $fcmService->sendToToken($token, $title, $bodyPreview, $data);
            }
        } catch (\Throwable $e) {
            // Catch all exceptions to prevent this job from failing indefinitely 
            // or affecting the overall system if FCM is completely down.
            Log::error('SendChatMessageNotification failed: ' . $e->getMessage());
        }
    }
}
