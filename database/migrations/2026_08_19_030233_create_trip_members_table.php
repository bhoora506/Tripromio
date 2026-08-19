<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Deletion behavior:
     *   trip_id → CASCADE: if a trip is deleted, its member rows are meaningless.
     *   user_id → RESTRICT: preserve membership history for reviews/trust audit.
     *
     * joined_at: nullable so the owner's row created at trip-creation time
     *   can record the creation timestamp rather than a "join" event.
     *
     * role and status: VARCHAR controlled by MemberRole / MemberStatus enums.
     *
     * The unique(trip_id, user_id) constraint prevents duplicate memberships.
     * Application logic must additionally enforce one-owner-per-trip invariant.
     */
    public function up(): void
    {
        Schema::create('trip_members', function (Blueprint $table) {
            $table->id();

            $table->foreignId('trip_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained()
                ->restrictOnDelete();

            // Controlled by MemberRole enum: 'owner' | 'member'
            $table->string('role', 20)->default('member');

            // Controlled by MemberStatus enum: 'active' | 'left' | 'removed'
            $table->string('status', 20)->default('active');

            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            // ── Constraints ──────────────────────────────────────────────────
            // Prevent duplicate membership rows for the same user on the same trip.
            $table->unique(['trip_id', 'user_id']);

            // ── Indexes ──────────────────────────────────────────────────────
            $table->index('trip_id');
            $table->index('user_id');
            $table->index('status');
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_members');
    }
};
