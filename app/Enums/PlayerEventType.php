<?php

namespace App\Enums;

enum PlayerEventType: string
{
    case Goal          = 'goal';
    case Assist        = 'assist';
    case Yellow        = 'yellow';
    case Red           = 'red';
    case OwnGoal       = 'own_goal';
    case PenaltySaved  = 'penalty_saved';
    case SubOn         = 'sub_on';
    case SubOff        = 'sub_off';

    case PenaltyMiss   = 'penalty_miss';

    public function label(): string
    {
        return match($this) {
            self::Goal         => 'Goal',
            self::Assist       => 'Assist',
            self::Yellow       => 'Yellow Card',
            self::Red          => 'Red Card',
            self::OwnGoal      => 'Own Goal',
            self::PenaltySaved => 'Penalty Saved',
            self::SubOn        => 'Substitution On',
            self::SubOff       => 'Substitution Off',
            self::PenaltyMiss  => 'Penalty Miss',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::Goal         => 'sports_soccer',
            self::Assist       => 'handshake',
            self::Yellow       => 'style',
            self::Red          => 'style',
            self::OwnGoal      => 'sports_soccer',
            self::PenaltySaved => 'front_hand',
            self::SubOn        => 'arrow_circle_up',
            self::SubOff       => 'arrow_circle_down',
            self::PenaltyMiss  => 'block',
        };
    }

    /** Fantasy points awarded for this event */
    public function fantasyPoints(): int
    {
        return match($this) {
            self::Goal         => 6,
            self::Assist       => 3,
            self::PenaltySaved => 5,
            self::Yellow       => -1,
            self::Red          => -3,
            self::OwnGoal      => -2,
            self::SubOn, self::SubOff => 0,
            self::PenaltyMiss  => -2,
        };
    }
}
