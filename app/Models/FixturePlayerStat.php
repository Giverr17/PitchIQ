<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FixturePlayerStat extends Model
{
    use HasFactory;

    protected $fillable = [
        'fixture_id',
        'player_id',
        'minutes_played',
        'saves',
        'bonus',
    ];

    public function fixture(): BelongsTo
    {
        return $this->belongsTo(Fixture::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}