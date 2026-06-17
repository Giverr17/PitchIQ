<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FantasyTeam extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'tournament_id',
        'fixture_id',
        'team_name',
        'formation',
        'total_points',
        'budget_remaining',
    ];

    // ─── Relationships ─────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function fixture(): BelongsTo
    {
        return $this->belongsTo(Fixture::class);
    }

    public function fantasyPicks(): HasMany
    {
        return $this->hasMany(FantasyPick::class);
    }
}
