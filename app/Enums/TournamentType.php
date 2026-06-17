<?php

namespace App\Enums;

enum TournamentType: string
{
    case FacultyCup         = 'faculty_cup';
    case DepartmentalLeague = 'departmental_league';
    case Friendly           = 'friendly';

    public function label(): string
    {
        return match($this) {
            self::FacultyCup         => 'Faculty Cup',
            self::DepartmentalLeague => 'Departmental League',
            self::Friendly           => 'Friendly',
        };
    }
}
