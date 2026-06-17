<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FantasyPick extends Model
{
    use HasFactory;

    protected $fillable = [
        'fantasy_team_id',
        'fixture_id',
        'player_id',
        'matchday',
        'is_captain',
        'is_vice_captain',
        'points_scored',
    ];

    protected $casts = [
        'is_captain'      => 'boolean',
        'is_vice_captain' => 'boolean',
    ];

    // ─── Relationships ─────────────────────────────────────────────────────────

    public function fantasyTeam(): BelongsTo
    {
        return $this->belongsTo(FantasyTeam::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function fixture(): BelongsTo
    {
        return $this->belongsTo(Fixture::class);
    }
}
