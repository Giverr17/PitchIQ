<?php

namespace App\Enums;

enum FixtureStatus: string
{
    case Scheduled  = 'scheduled';
    case Live       = 'live';
    case Completed  = 'completed';
    case Postponed  = 'postponed';

    public function label(): string
    {
        return match($this) {
            self::Scheduled => 'Scheduled',
            self::Live      => 'Live',
            self::Completed => 'Completed',
            self::Postponed => 'Postponed',
        };
    }

    /** True when users may still submit predictions */
    public function isPredictable(): bool
    {
        return match($this) {
            self::Scheduled => true,
            default         => false,
        };
    }

    /** CSS badge classes for UI */
    public function badgeClass(): string
    {
        return match($this) {
            self::Live      => 'bg-error/15 text-error border border-error/30 animate-pulse',
            self::Scheduled => 'bg-primary-container/10 text-primary-container border border-primary-container/20',
            self::Postponed => 'bg-secondary-container/10 text-secondary-container border border-secondary-container/25',
            self::Completed => 'bg-surface-container text-on-surface-variant border border-outline-variant/20',
        };
    }
}
