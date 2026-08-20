<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function profile(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function interests(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Interest::class, 'user_interests')->withTimestamps();
    }

    /**
     * Trips created/owned by this user (via trips.user_id).
     */
    public function trips(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Trip::class, 'user_id');
    }

    /**
     * All TripMember rows for this user (owner + member rows, all trips, all statuses).
     */
    public function tripMembers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TripMember::class);
    }

    /**
     * Trips the user is an active member of (including trips they own).
     * Useful for "my trips" feed.
     */
    public function tripsJoined(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Trip::class, 'trip_members')
            ->withPivot(['role', 'status', 'joined_at'])
            ->withTimestamps();
    }

    /**
     * Travel availability windows for this user.
     * A user may have multiple non-overlapping or overlapping windows.
     */
    public function travelAvailabilities(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TravelAvailability::class);
    }

    /**
     * Preferred destinations for the user.
     */
    public function preferredDestinations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PreferredDestination::class);
    }
}
