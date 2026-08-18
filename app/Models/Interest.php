<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'slug'])]
class Interest extends Model
{
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_interests')->withTimestamps();
    }
}
