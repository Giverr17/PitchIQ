<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    protected $fillable = ['key', 'value', 'label', 'description'];

    // ─── Setting keys ──────────────────────────────────────────────────────────
    const FANTASY_BUDGET = 'fantasy_budget';

    // ─── Helper ────────────────────────────────────────────────────────────────

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = static::where('key', $key)->value('value');
        return $value !== null ? $value : $default;
    }
}
