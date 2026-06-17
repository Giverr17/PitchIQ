<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class CampusAd extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_name',
        'image_path',
        'link_url',
        'is_active',
        'starts_at',
        'ends_at',
        'clicks',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'starts_at' => 'date',
        'ends_at'   => 'date',
    ];

    // Query scope: only ads that should show right now
    public function scopeLive(Builder $query): Builder
    {
        $today = now()->toDateString();
        return $query->where('is_active', true)
            ->where(fn($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $today))
            ->where(fn($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $today));
    }
}