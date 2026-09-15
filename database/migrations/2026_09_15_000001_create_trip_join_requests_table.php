<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This table stores the request workflow for a user asking to join a trip.
     * It is intentionally separate from trip_members, which stores CONFIRMED
     * memberships only.
     *
     * Allowed statuses (enforced at app layer via JoinRequestStatus enum):
     *   pending   — awaiting owner decision
     *   approved  — owner approved; a trip_members row is created atomically
     *   rejected  — owner rejected the request
     *   cancelled — requester cancelled their own pending request
     *
     * Deletion behavior:
     *   trip_id → CASCADE: request history has no meaning without the trip.
     *   user_id → RESTRICT: preserve audit trail (Phase 7+ account-deletion handling).
     *
     * Uniqueness:
     *   No DB-level unique(trip_id, user_id) because a user may submit a fresh
     *   request after a previous rejected/cancelled one. Application logic enforces
     *   that only ONE pending request exists per (trip_id, user_id) pair.
     */
    public function up(): void
    {
        Schema::create('trip_join_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('trip_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained()
                ->restrictOnDelete();

            // Controlled by JoinRequestStatus enum: 'pending' | 'approved' | 'rejected' | 'cancelled'
            $table->string('status', 20)->default('pending');

            $table->timestamps();

            // ── Indexes ──────────────────────────────────────────────────────
            $table->index('trip_id');
            $table->index('user_id');
            $table->index('status');
            // Composite for owner's inbox query: pending requests for a trip
            $table->index(['trip_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_join_requests');
    }
};
