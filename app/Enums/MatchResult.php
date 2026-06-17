<?php

namespace App\Enums;

enum MatchResult: string
{
    case Home = 'home';
    case Draw = 'draw';
    case Away = 'away';

    public function label(): string
    {
        return match($this) {
            self::Home => 'Home Win',
            self::Draw => 'Draw',
            self::Away => 'Away Win',
        };
    }
}
