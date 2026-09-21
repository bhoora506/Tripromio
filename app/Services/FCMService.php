<?php

namespace App\Services;

use App\Models\UserDevice;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FCMService
{
    private const FCM_API_URL = 'https://fcm.googleapis.com/v1/projects/%s/messages:send';
    private const FCM_SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    /**
     * Send a notification to a specific device token.
     *
     * @param string $token FCM device token
     * @param string $title Notification title
     * @param string $body Notification body
     * @param array $data Additional data payload
     * @return bool True if successful, false otherwise
     */
    public function sendToToken(string $token, string $title, string $body, array $data = []): bool
    {
        $projectId = env('FIREBASE_PROJECT_ID');
        if (empty($projectId)) {
            Log::warning('FCM Push failed: FIREBASE_PROJECT_ID is not configured.');
            return false;
        }

        try {
            $accessToken = $this->getAccessToken();
            if (!$accessToken) {
                return false;
            }

            $url = sprintf(self::FCM_API_URL, $projectId);

            $payload = [
                'message' => [
                    'token' => $token,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'data' => $data,
                ],
            ];

            $response = Http::withToken($accessToken)
                ->timeout(10) // Fast timeout, don't block
                ->post($url, $payload);

            if ($response->successful()) {
                return true;
            }

            $this->handleFCMError($response, $token);
            return false;
        } catch (\Throwable $e) {
            Log::error('FCM Push Exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Handle FCM API error responses and clean up invalid tokens.
     */
    protected function handleFCMError($response, string $token): void
    {
        $status = $response->status();
        $error = $response->json('error.details.0.errorCode') ?? $response->json('error.status');
        
        // Unregistered or invalid tokens should be cleaned up safely
        if ($status === 404 || $status === 400 || $error === 'UNREGISTERED' || $error === 'INVALID_ARGUMENT') {
            Log::info("FCM Token invalid or unregistered. Removing from database. Reason: {$error}");
            UserDevice::where('fcm_token', $token)->delete();
        } else {
            Log::warning("FCM Push HTTP Error: {$status} - " . $response->body());
        }
    }

    /**
     * Generate a short-lived OAuth 2.0 access token using google/auth.
     */
    protected function getAccessToken(): ?string
    {
        $projectId = env('FIREBASE_PROJECT_ID');
        $clientEmail = env('FIREBASE_CLIENT_EMAIL');
        $privateKey = env('FIREBASE_PRIVATE_KEY');

        if (empty($projectId) || empty($clientEmail) || empty($privateKey)) {
            Log::warning('FCM Auth failed: Missing Firebase credentials in .env.');
            return null;
        }

        // Handle string escaped newlines in .env
        $privateKey = str_replace('\\n', "\n", $privateKey);

        try {
            $credentials = new ServiceAccountCredentials(
                [self::FCM_SCOPE],
                [
                    'client_email' => $clientEmail,
                    'private_key' => $privateKey,
                    'project_id' => $projectId,
                ]
            );

            $token = $credentials->fetchAuthToken();
            
            return $token['access_token'] ?? null;
        } catch (\Throwable $e) {
            Log::error('FCM OAuth Token Generation Failed: ' . $e->getMessage());
            return null;
        }
    }
}
