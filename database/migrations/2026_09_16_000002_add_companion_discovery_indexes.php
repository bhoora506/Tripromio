<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds missing indexes identified in the Phase F companion-discovery audit.
 *
 * Indexes added:
 *   user_profiles.travel_style   — filter companions by travel style
 *   user_profiles.country        — filter companions by country
 *   preferred_destinations.user_id — join/filter by user when matching destinations
 *   users.email_verified_at      — filter to verified users only in companion discovery
 *
 * All columns already exist in the schema; this migration adds indexes only.
 * No existing data or constraints are modified.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->index('travel_style');
            $table->index('country');
        });

        Schema::table('preferred_destinations', function (Blueprint $table) {
            $table->index('user_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('email_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropIndex(['travel_style']);
            $table->dropIndex(['country']);
        });

        Schema::table('preferred_destinations', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['email_verified_at']);
        });
    }
};
