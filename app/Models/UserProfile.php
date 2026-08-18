<?php

namespace App\Models;

use App\Enums\TravelStyle;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['user_id', 'profile_photo_path', 'bio', 'city', 'country', 'languages', 'travel_style'])]
class UserProfile extends Model
{
    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'languages'   => 'array',
            'travel_style' => TravelStyle::class,
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Computed Accessors ────────────────────────────────────────────────────

    /**
     * Return a publicly accessible URL for the profile photo, or null.
     */
    public function getProfilePhotoUrlAttribute(): ?string
    {
        if (! $this->profile_photo_path) {
            return null;
        }

        return Storage::disk('public')->url($this->profile_photo_path);
    }
}
