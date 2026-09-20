<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents a registered FCM device token for a user.
 *
 * Each row stores a single FCM registration token associated with an
 * authenticated user and their device platform. The fcm_token column
 * has a UNIQUE constraint so the same physical device cannot be
 * registered to multiple users simultaneously.
 *
 * @property int         $id
 * @property int         $user_id
 * @property string      $fcm_token
 * @property string      $platform   'android' | 'ios'
 * @property \Carbon\Carbon|null $last_used_at
 * @property \Carbon\Carbon      $created_at
 * @property \Carbon\Carbon      $updated_at
 */
class UserDevice extends Model
{
    protected $fillable = [
        'user_id',
        'fcm_token',
        'platform',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
