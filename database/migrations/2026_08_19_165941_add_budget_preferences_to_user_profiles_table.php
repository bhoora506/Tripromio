<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds budget preference fields to user_profiles.
 *
 * Semantics:
 *   preferred_budget_min / preferred_budget_max represent the typical budget
 *   range a traveller is comfortable with for a trip. These are preferences,
 *   not guarantees. Both are optional.
 *
 * Matching engine use (Phase 3B+):
 *   These fields enable budget-compatibility scoring between trip budgets and
 *   candidate budget preferences without requiring a separate preferences table.
 *
 * NOT included in profile completion score: these are optional travel
 * preferences and should not make a user appear "incomplete".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            // Budget preferences: nullable, currency-agnostic (INR at app layer)
            $table->decimal('preferred_budget_min', 12, 2)->nullable()->after('travel_style');
            $table->decimal('preferred_budget_max', 12, 2)->nullable()->after('preferred_budget_min');
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn(['preferred_budget_min', 'preferred_budget_max']);
        });
    }
};
