<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds is_discoverable to user_profiles.
 *
 * Semantics:
 *   true  — user appears in companion discovery results (default).
 *   false — user has opted out; invisible to other users in companion search.
 *
 * Default true ensures all existing rows remain discoverable after migration
 * without requiring a data back-fill.
 *
 * This field is managed by the user (future F2/F4 profile API update).
 * It is NOT exposed through the discovery response — it is a server-side
 * filter criterion only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->boolean('is_discoverable')->default(true)->after('preferred_budget_max');
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn('is_discoverable');
        });
    }
};
