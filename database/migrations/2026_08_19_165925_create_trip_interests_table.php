<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot table linking trips to interests.
 *
 * Reuses the existing `interests` master table from Phase 1B.
 * No duplicate interest definitions are created here.
 *
 * Foreign-key deletion behavior:
 *   trip_id     → cascade  (trip deleted → remove its interest associations)
 *   interest_id → cascade  (interest removed from master list → remove associations)
 *
 * Unique constraint prevents duplicate trip↔interest pairs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_interests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('trip_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('interest_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->timestamps();

            // Prevent duplicate associations
            $table->unique(['trip_id', 'interest_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_interests');
    }
};
