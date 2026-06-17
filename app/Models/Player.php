<?php

namespace App\Models;

use App\Enums\PlayerPosition;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Player extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'name',
        'position',
        'number',
        'fantasy_price',
        'goals',
        'assists',
        'yellow_cards',
        'red_cards',
    ];

    protected $casts = [
        'position' => PlayerPosition::class,
    ];

    // ─── Relationships ─────────────────────────────────────────────────────────

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function playerEvents(): HasMany
    {
        return $this->hasMany(PlayerEvent::class);
    }

    public function fantasyPicks(): HasMany
    {
        return $this->hasMany(FantasyPick::class);
    }
    public function fixturePlayerStats(): HasMany
    {
        return $this->hasMany(FixturePlayerStat::class);
    }
}
