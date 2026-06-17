<?php

namespace App\Models;

use App\Enums\TournamentStatus;
use App\Enums\TournamentType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tournament extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'season',
        'status',
        'active_matchday',
        'squad_size',
        'start_date',
    ]; 

    protected $casts = [
        'start_date' => 'date',
        'status' => TournamentStatus::class,
        'type' => TournamentType::class,
    ];

    // ─── Relationships ─────────────────────────────────────────────────────────

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function fixtures(): HasMany
    {
        return $this->hasMany(Fixture::class);
    }

    public function fantasyTeams(): HasMany
    {
        return $this->hasMany(FantasyTeam::class);
    }

    public function miniLeagues(): HasMany
    {
        return $this->hasMany(MiniLeague::class);
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    public function isPredictable(): bool
    {
        return $this->status->isPredictable();
    }

    public function airtimePayouts(): HasMany
    {
        return $this->hasMany(AirtimePayout::class);
    }
}
