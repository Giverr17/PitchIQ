<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TokenCost extends Model
{
    protected $fillable = ['feature', 'label', 'description', 'cost'];

    // ─── Feature keys ──────────────────────────────────────────────────────────
    const PREDICTION    = 'prediction';
    const SQUAD_BUILDER = 'squad_builder';
    const GAME          = 'game';

    // ─── Helper ────────────────────────────────────────────────────────────────

    public static function costFor(string $feature): int
    {
        return (int) (static::where('feature', $feature)->value('cost') ?? 0);
    }
}
