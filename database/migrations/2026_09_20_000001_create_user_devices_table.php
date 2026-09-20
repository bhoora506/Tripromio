<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the user_devices table for FCM device-token registration.
 *
 * Design decisions:
 *
 * 1. UNIQUE on fcm_token (not on user_id + fcm_token):
 *    A physical device (token) belongs to exactly one active user at a time.
 *    When a user logs out and another logs in on the same device, the existing
 *    row should be reassigned rather than creating a duplicate. A unique index
 *    on the token alone enforces this and prevents duplicate active records.
 *
 * 2. CASCADE on user delete:
 *    Unlike connection history (which uses RESTRICT for audit), device tokens
 *    are operational data. When a user account is deleted their device tokens
 *    should be removed automatically.
 *
 * 3. platform: VARCHAR (not DB ENUM) matching project convention (TripStatus,
 *    JoinRequestStatus, ConnectionStatus are all VARCHAR-backed strings).
 *    Application layer validates values via RegisterDeviceTokenRequest.
 *
 * 4. last_used_at: nullable timestamp updated on every successful registration.
 *    Useful for future cleanup of stale tokens without a scheduled job in H1-B.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_devices', function (Blueprint $table) {
            $table->id();

            // The authenticated user this device token belongs to.
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // The FCM registration token — unique across the table so a single
            // physical device cannot be registered to multiple users at once.
            // 255 characters provides sufficient headroom for current FCM token
            // formats (~152-163 chars) and fits within MySQL's 3072-byte
            // unique-key limit (255 × 4 bytes = 1020 bytes < 3072 bytes limit).
            $table->string('fcm_token', 255);

            // Platform identifier: 'android' | 'ios'
            $table->string('platform', 10);

            // Updated on every successful upsert — useful for stale-token cleanup.
            $table->timestamp('last_used_at')->nullable();

            $table->timestamps();

            // ── Indexes ──────────────────────────────────────────────────────

            // Primary lookup: find a device by its FCM token.
            $table->unique('fcm_token');

            // Secondary lookup: find all devices for a user (e.g., on logout).
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_devices');
    }
};
