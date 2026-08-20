<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Travel availability windows for users.
 *
 * Design decisions:
 *
 * 1. SEPARATE TABLE — not inline in user_profiles.
 *    A traveller may have multiple availability windows (e.g. Sep 20–30,
 *    Oct 10–15, Nov 1–5). A single date-range column cannot represent this.
 *
 * 2. DATE COLUMNS — not datetime.
 *    Availability is expressed in day-level granularity. Sub-day precision
 *    is not needed and would complicate timezone handling unnecessarily.
 *
 * 3. FOREIGN KEY BEHAVIOR: cascade on user deletion.
 *    If a user account is permanently removed, their availability windows
 *    have no meaning and can be safely removed.
 *
 * 4. INDEXES on user_id, start_date, end_date support the expected matching
 *    query: "find candidates available during trip dates".
 *
 * NOT part of profile completion score: availability is an optional input
 * and varies frequently — a missing availability window doesn't mean an
 * incomplete profile.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('travel_availabilities', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->date('start_date');
            $table->date('end_date');

            $table->timestamps();

            // Indexes for matching queries
            $table->index('user_id');
            $table->index('start_date');
            $table->index('end_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_availabilities');
    }
};
