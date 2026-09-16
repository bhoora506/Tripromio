<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the connection_requests table.
 *
 * Design decisions:
 *
 * 1. RESTRICT on user delete (both sides):
 *    Connection history is meaningful for trust/audit. Deletion is handled
 *    explicitly in a future account-deletion workflow (Phase 7+), matching
 *    the convention used by trip_join_requests.user_id.
 *
 * 2. NO UNIQUE(requester_id, recipient_id):
 *    A DB-level unique on the pair would prevent legitimate re-requests after
 *    a rejected or cancelled status — the same pattern as trip_join_requests.
 *    Application logic (ConnectionRequestService) enforces that only ONE
 *    pending request exists per (requester_id, recipient_id) pair at a time.
 *
 * 3. SELF-REQUEST PREVENTION:
 *    MySQL/SQLite do not support CHECK constraints that reference other columns
 *    in a way that is reliably portable in Laravel migrations. The application
 *    layer (ConnectionRequestService + ConnectionRequestPolicy) enforces
 *    requester_id != recipient_id. A DB-level check is not added here because
 *    the project currently supports both MySQL (production) and SQLite (tests),
 *    and a portable CHECK constraint would require raw SQL that differs between
 *    engines. This limitation is documented and mitigated at the service layer.
 *
 * 4. COMPOSITE INDEX on (requester_id, status) and (recipient_id, status):
 *    These support the expected inbox/sent queries in ConnectionRequestService.
 *
 * 5. status: controlled by ConnectionStatus enum at the application layer.
 *    VARCHAR is used (not DB ENUM) matching the convention of JoinRequestStatus,
 *    MemberStatus, and TripStatus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connection_requests', function (Blueprint $table) {
            $table->id();

            // The user who initiates the connection request
            $table->foreignId('requester_id')
                ->constrained('users')
                ->restrictOnDelete();

            // The user being invited to connect
            $table->foreignId('recipient_id')
                ->constrained('users')
                ->restrictOnDelete();

            // Controlled by ConnectionStatus enum: 'pending' | 'accepted' | 'rejected' | 'cancelled'
            $table->string('status', 20)->default('pending');

            $table->timestamps();

            // ── Indexes ──────────────────────────────────────────────────────
            $table->index('requester_id');
            $table->index('recipient_id');
            $table->index('status');

            // Composite for sent-request queries: "pending requests sent by user X"
            $table->index(['requester_id', 'status']);

            // Composite for inbox queries: "pending requests received by user Y"
            $table->index(['recipient_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connection_requests');
    }
};
