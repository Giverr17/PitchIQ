<?php

namespace App\Enums;

enum TournamentStatus: string
{
    case Upcoming  = 'upcoming';
    case Active    = 'active';
    case Completed = 'completed';

    public function label(): string
    {
        return match($this) {
            self::Upcoming  => 'Upcoming',
            self::Active    => 'Active',
            self::Completed => 'Completed',
        };
    }

    /** True when tournament-level predictions may still be submitted/edited */
    public function isPredictable(): bool
    {
        return match($this) {
            self::Upcoming, self::Active => true,
            self::Completed              => false,
        };
    }

    public function badgeClass(): string
    {
        return match($this) {
            self::Active    => 'bg-primary-container/10 text-primary-container border border-primary-container/20',
            self::Upcoming  => 'bg-secondary-container/10 text-secondary-container border border-secondary-container/25',
            self::Completed => 'bg-surface-container text-on-surface-variant border border-outline-variant/20',
        };
    }
}
