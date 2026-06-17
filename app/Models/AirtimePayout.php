<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AirtimePayout extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'tournament_id', 'matchday', 'scope',
        'rank', 'phone', 'amount', 'status', 'provider_reference', 'notes',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }
}