<?php

namespace Tests\Feature\Chat;

use App\Enums\ConnectionStatus;
use App\Jobs\SendChatMessageNotification;
use App\Models\ConnectionRequest;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\FCMService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PushNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $sender;
    private User $recipient;
    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sender = User::factory()->create();
        $this->recipient = User::factory()->create();

        // Must have accepted connection
        ConnectionRequest::create([
            'requester_id' => $this->sender->id,
            'recipient_id' => $this->recipient->id,
            'status'       => ConnectionStatus::Accepted->value,
        ]);

        $this->conversation = Conversation::create([
            'requester_id' => $this->sender->id,
            'recipient_id' => $this->recipient->id,
        ]);
        
        // Add a device for recipient
        UserDevice::create([
            'user_id' => $this->recipient->id,
            'fcm_token' => 'valid-fcm-token-123',
            'platform' => 'android',
        ]);
    }

    public function test_message_creation_dispatches_notification_job()
    {
        Queue::fake();

        $response = $this->actingAs($this->sender)
            ->postJson("/api/conversations/{$this->conversation->id}/messages", [
                'body' => 'Hello there!',
            ]);

        $response->assertStatus(201);

        Queue::assertPushed(SendChatMessageNotification::class, function ($job) {
            return $job->message->body === 'Hello there!';
        });
    }

    public function test_job_sends_fcm_and_cleans_up_invalid_tokens()
    {
        // Add an invalid device
        UserDevice::create([
            'user_id' => $this->recipient->id,
            'fcm_token' => 'invalid-token-456',
            'platform' => 'ios',
        ]);

        // Mock Http to simulate FCM
        Http::fake([
            'fcm.googleapis.com/*' => function ($request) {
                $payload = json_decode($request->body(), true);
                $token = $payload['message']['token'];

                if ($token === 'invalid-token-456') {
                    return Http::response([
                        'error' => [
                            'status' => 'NOT_FOUND',
                            'details' => [
                                ['errorCode' => 'UNREGISTERED']
                            ]
                        ]
                    ], 404);
                }

                return Http::response(['name' => 'projects/test/messages/abc'], 200);
            },
        ]);

        // Mock getAccessToken inside FCMService via reflection or just put dummy .env
        putenv('FIREBASE_PROJECT_ID=test');
        putenv('FIREBASE_CLIENT_EMAIL=test@test.com');
        putenv('FIREBASE_PRIVATE_KEY=fake');

        // We will mock the getAccessToken method since we don't want real Google API calls
        $fcmService = $this->partialMock(FCMService::class, function ($mock) {
            $mock->shouldAllowMockingProtectedMethods();
            $mock->shouldReceive('getAccessToken')->andReturn('fake-oauth-token');
        });

        $message = Message::create([
            'conversation_id' => $this->conversation->id,
            'sender_id'       => $this->sender->id,
            'body'            => 'Test push',
        ]);

        $job = new SendChatMessageNotification($message);
        $job->handle($fcmService);

        // Check if invalid token was removed
        $this->assertDatabaseMissing('user_devices', ['fcm_token' => 'invalid-token-456']);
        // Check if valid token was kept
        $this->assertDatabaseHas('user_devices', ['fcm_token' => 'valid-fcm-token-123']);
    }

    public function test_show_conversation_endpoint()
    {
        $response = $this->actingAs($this->sender)
            ->getJson("/api/conversations/{$this->conversation->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.conversation.id', $this->conversation->id);
            
        // Third user cannot view
        $thirdUser = User::factory()->create();
        $this->actingAs($thirdUser)
            ->getJson("/api/conversations/{$this->conversation->id}")
            ->assertStatus(403);
    }
}
