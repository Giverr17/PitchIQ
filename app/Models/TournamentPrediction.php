<?php

namespace App\Models;

use App\Enums\TournamentPredictionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentPrediction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'tournament_id',
        'type',
        'predicted_player_id',
        'points_earned',
        'verified_at',
    ];

    protected $casts = [
        'type'        => TournamentPredictionType::class,
        'verified_at' => 'datetime',
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

    public function predictedPlayer(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'predicted_player_id');
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    public function isLocked(): bool
    {
        return $this->verified_at !== null;
    }
}
