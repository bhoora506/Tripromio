<?php

namespace App\Models;

use Database\Factories\TravelAvailabilityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents a travel availability window for a user.
 *
 * A user may have multiple overlapping or consecutive windows.
 * The matching engine uses these to determine whether a candidate
 * is available during a specific trip's date range.
 *
 * Validation invariant: start_date <= end_date (enforced at request layer).
 */
#[Fillable(['user_id', 'start_date', 'end_date'])]
class TravelAvailability extends Model
{
    /** @use HasFactory<TravelAvailabilityFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date'   => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
