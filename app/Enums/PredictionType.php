<?php

namespace App\Enums;

enum PredictionType: string
{
    case Result          = 'result';
    case ExactScore      = 'exact_score';
    case FirstGoalscorer = 'first_goalscorer';
    case CleanSheet      = 'clean_sheet';
    case CardedPlayer    = 'carded_player';

    /** Human-readable label for UI display */
    public function label(): string
    {
        return match($this) {
            self::Result          => 'Match Result',
            self::ExactScore      => 'Exact Score',
            self::FirstGoalscorer => 'First Goalscorer',
            self::CleanSheet      => 'Clean Sheet',
            self::CardedPlayer    => 'Player to be Carded',
        };
    }

    /** [value => label] map for <select> dropdowns */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn(self $c) => [$c->value => $c->label()])
            ->all();
    }

    /** Material Symbol icon name for UI */
    public function icon(): string
    {
        return match($this) {
            self::Result          => 'scoreboard',
            self::ExactScore      => '123',
            self::FirstGoalscorer => 'sports_soccer',
            self::CleanSheet      => 'shield',
            self::CardedPlayer    => 'style',
        };
    }
}
