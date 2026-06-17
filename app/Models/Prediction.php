<?php

namespace App\Models;

use App\Enums\PredictionType;
use App\Enums\MatchResult;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Prediction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'fixture_id',
        'type',
        'predicted_result',
        'predicted_home_score',
        'predicted_away_score',
        'predicted_scorer_id',
        'predicted_team_id',
        'points_earned',
        'verified_at',
    ];

    protected $casts = [
        'type'             => PredictionType::class,
        'predicted_result' => MatchResult::class,
        'verified_at'      => 'datetime',
    ];

    // ─── Relationships ─────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fixture(): BelongsTo
    {
        return $this->belongsTo(Fixture::class);
    }

    public function predictedScorer(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'predicted_scorer_id');
    }

    public function predictedTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'predicted_team_id');
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    public function isLocked(): bool
    {
        return $this->verified_at !== null;
    }
}
