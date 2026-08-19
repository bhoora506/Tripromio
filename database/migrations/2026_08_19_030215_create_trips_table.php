<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * max_members semantics: total travellers including the owner.
     *   e.g. max_members = 4 → owner + 3 members.
     *
     * user_id (trip owner) is NOT cascade-deleted with the user because
     * preserving trip history is important for trust/reviews. A future
     * account-deletion workflow will handle orphaned trips explicitly.
     *
     * trip_type and status are VARCHAR; the application enforces allowed
     * values via backed enums (TripType, TripStatus). DB-level ENUMs are
     * avoided because they require a schema change to extend.
     */
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table) {
            $table->id();

            // Ownership — restrict (not cascade) so trip history is preserved
            // even if the owner account is later deleted/deactivated.
            $table->foreignId('user_id')
                ->constrained()
                ->restrictOnDelete();

            $table->string('title', 200);
            $table->string('destination', 200);

            // Future Google Places integration
            $table->string('place_id', 100)->nullable();

            // Decimal coordinates: 7 decimal places ≈ 1 cm precision
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->date('start_date');
            $table->date('end_date');

            // Budget in INR at application layer; schema stays currency-agnostic
            $table->decimal('budget_min', 12, 2)->nullable();
            $table->decimal('budget_max', 12, 2)->nullable();

            // Controlled by TripType enum at application layer
            $table->string('trip_type', 50);

            $table->text('description')->nullable();

            // Total travellers including owner; validated ≥ 1 and ≤ 20 at app layer
            $table->unsignedTinyInteger('max_members')->default(2);

            // Controlled by TripStatus enum at application layer; default draft
            $table->string('status', 20)->default('draft');

            $table->timestamps();

            // ── Indexes for future discovery/matching queries ────────────────
            $table->index('user_id');
            $table->index('destination');
            $table->index('start_date');
            $table->index('end_date');
            $table->index('status');
            $table->index('trip_type');
            // Composite for common date-range discovery queries
            $table->index(['status', 'start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};
