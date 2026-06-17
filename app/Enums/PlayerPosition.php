<?php

namespace App\Enums;

enum PlayerPosition: string
{
    case Goalkeeper = 'GK';
    case Defender   = 'DEF';
    case Midfielder = 'MID';
    case Forward    = 'FWD';

    public function label(): string
    {
        return match($this) {
            self::Goalkeeper => 'Goalkeeper',
            self::Defender   => 'Defender',
            self::Midfielder => 'Midfielder',
            self::Forward    => 'Forward',
        };
    }

    public function shortLabel(): string
    {
        return $this->value;
    }

    /** Fantasy points multiplier for this position */
    public function pointsMultiplier(): float
    {
        return match($this) {
            self::Goalkeeper => 1.5,
            self::Defender   => 1.3,
            self::Midfielder => 1.1,
            self::Forward    => 1.0,
        };
    }
}
