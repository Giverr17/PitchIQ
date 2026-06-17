<?php

namespace App\Models;

use App\Enums\PlayerEventType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'fixture_id',
        'player_id',
        'event_type',
        'minute',
        'is_substitute',
    ];

    protected $casts = [
        'event_type'   => PlayerEventType::class,
        'is_substitute' => 'boolean',
    ];

    // ─── Relationships ─────────────────────────────────────────────────────────

    public function fixture(): BelongsTo
    {
        return $this->belongsTo(Fixture::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
