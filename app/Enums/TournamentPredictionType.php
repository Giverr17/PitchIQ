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

    /** [value => label] map for <select> dropdowns */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn(self $c) => [$c->value => $c->label()])
            ->all();
    }

    public function icon(): string
    {
        return match($this) {
            self::TopScorer   => 'military_tech',
            self::MostAssists => 'handshake',
        };
    }
}
