<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the conversations table.
 *
 * Design decisions:
 *
 * 1. CANONICAL ORDERING to prevent duplicate A↔B / B↔A conversations:
 *    We store participants as (requester_id, recipient_id) where
 *    requester_id is ALWAYS the smaller user ID numerically.
 *    This is enforced at the service layer (ConversationService::findOrCreate).
 *    A UNIQUE(requester_id, recipient_id) constraint then guarantees that
 *    only one row can exist per pair regardless of which user initiates.
 *
 *    Example: user 5 and user 12 always produces (requester_id=5, recipient_id=12).
 *
 * 2. RESTRICT on user delete (both sides):
 *    Conversation history is meaningful for trust/audit. Matches the convention
 *    used by connection_requests.
 *
 * 3. BUSINESS MEANING of requester_id / recipient_id:
 *    Due to canonical ordering these do NOT necessarily reflect who initiated
 *    the conversation. Both are simply "participants". The chat UI should treat
 *    both equally. The authorized pair is enforced via ConnectionRequest status.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();

            // Participant A (lower user ID due to canonical ordering)
            $table->foreignId('requester_id')
                ->constrained('users')
                ->restrictOnDelete();

            // Participant B (higher user ID due to canonical ordering)
            $table->foreignId('recipient_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();

            // ── Indexes ──────────────────────────────────────────────────────
            $table->index('requester_id');
            $table->index('recipient_id');

            // Canonical uniqueness: one conversation per user pair.
            // Because we always store min(A,B) as requester_id and max(A,B)
            // as recipient_id, this unique constraint is sufficient for all
            // orderings of A and B.
            $table->unique(['requester_id', 'recipient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
