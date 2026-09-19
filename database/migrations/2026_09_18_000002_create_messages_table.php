<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the messages table.
 *
 * Design decisions:
 *
 * 1. CASCADE on conversation delete:
 *    Messages have no meaning without their conversation. If a conversation
 *    is ever removed, its messages are removed with it.
 *
 * 2. RESTRICT on sender delete:
 *    Preserve audit trail for account-deletion handling (Phase 7+).
 *    Mirrors the convention used by trip_join_requests and connection_requests.
 *
 * 3. read_at nullable datetime:
 *    NULL = unread. Set to a timestamp when the other participant reads.
 *    Only messages received by the other user are marked; own messages are
 *    never updated via the markAsRead path.
 *
 * 4. body TEXT:
 *    Supports arbitrary text length for MVP. No HTML/markdown.
 *    Maximum length enforced at the application layer (ConversationService).
 *
 * 5. Composite index (conversation_id, created_at):
 *    Supports the primary query: paginated message history for a conversation,
 *    newest-first or oldest-first.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('conversation_id')
                ->constrained('conversations')
                ->cascadeOnDelete();

            $table->foreignId('sender_id')
                ->constrained('users')
                ->restrictOnDelete();

            // Plain text message content — no HTML, no markdown for MVP.
            $table->text('body');

            // NULL = unread. Set when the OTHER participant reads the message.
            $table->timestamp('read_at')->nullable();

            $table->timestamps();

            // ── Indexes ──────────────────────────────────────────────────────
            $table->index('sender_id');

            // Primary query: message history for a conversation, sorted by time.
            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
