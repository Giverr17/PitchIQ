<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MiniLeague extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id',
        'owner_id',
        'name',
        'invite_code',
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    // Many-to-many: a league has many member users
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'mini_league_user')->withTimestamps();
    }
}