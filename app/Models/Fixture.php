<?php

namespace App\Models;

use App\Enums\FixtureStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fixture extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id',
        'home_team_id',
        'away_team_id',
        'matchday',
        'date',
        'status',
        'home_score',
        'away_score',
    ];

    protected $casts = [
        'date'   => 'datetime',
        'status' => FixtureStatus::class,
    ];

    // ─── Relationships ─────────────────────────────────────────────────────────

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }

    public function playerEvents(): HasMany
    {
        return $this->hasMany(PlayerEvent::class);
    }

    public function predictions(): HasMany
    {
        return $this->hasMany(Prediction::class);
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    public function isPredictable(): bool
    {
        return $this->status->isPredictable();
    }

    public function playerStats(): HasMany
    {
        return $this->hasMany(FixturePlayerStat::class);
    }

    public function fantasyTeams(): HasMany
    {
        return $this->hasMany(FantasyTeam::class);
    }

    public function fantasyPicks(): HasMany
    {
        return $this->hasMany(FantasyPick::class);
    }
}
