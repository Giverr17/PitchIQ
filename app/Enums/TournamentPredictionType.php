<?php

namespace App\Enums;

enum TournamentPredictionType: string
{
    case TopScorer   = 'top_scorer';
    case MostAssists = 'most_assists';

    public function label(): string
    {
        return match($this) {
            self::TopScorer   => 'Top Scorer',
            self::MostAssists => 'Most Assists',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::TopScorer   => 'military_tech',
            self::MostAssists => 'handshake',
        };
    }
}
