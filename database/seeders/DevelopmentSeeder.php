<?php

namespace Database\Seeders;

use App\Models\Fixture;
use App\Models\Player;
use App\Models\Team;
use App\Models\Tournament;
use Illuminate\Database\Seeder;

class DevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        // ─── Tournament ───────────────────────────────────────────────────────────
        $tournament = Tournament::updateOrCreate(
            ['name' => 'Faculty Cup 2025/2026'],
            [
                'type'       => 'faculty_cup',
                'season'     => '2025/2026',
                'status'     => 'active',
                'start_date' => now()->subDays(7)->toDateString(),
            ]
        );

        // ─── Teams ────────────────────────────────────────────────────────────────
        $teamA = Team::updateOrCreate(
            ['name' => 'CSC FC'],
            [
                'tournament_id' => $tournament->id,
                'faculty'       => 'Sciences',
                'department'    => 'Computer Science',
                'colour'        => '#00E676',
            ]
        );

        $teamB = Team::updateOrCreate(
            ['name' => 'EEE United'],
            [
                'tournament_id' => $tournament->id,
                'faculty'       => 'Engineering',
                'department'    => 'Electrical Engineering',
                'colour'        => '#3B82F6',
            ]
        );

        $teamC = Team::updateOrCreate(
            ['name' => 'MED Stars'],
            [
                'tournament_id' => $tournament->id,
                'faculty'       => 'Medicine',
                'department'    => 'Medical Sciences',
                'colour'        => '#F59E0B',
            ]
        );

        // ─── Players ──────────────────────────────────────────────────────────────
        // CSC FC — 8 players: 1 GK, 3 DEF, 2 MID, 2 FWD
        $this->seedPlayers($teamA->id, [
            ['name' => 'Emmanuel Okafor',    'position' => 'GK',  'number' => 1,  'fantasy_price' => 60],
            ['name' => 'Chukwuemeka Eze',    'position' => 'DEF', 'number' => 4,  'fantasy_price' => 55],
            ['name' => 'Adebayo Oladele',    'position' => 'DEF', 'number' => 5,  'fantasy_price' => 50],
            ['name' => 'Kelechi Nwosu',      'position' => 'DEF', 'number' => 6,  'fantasy_price' => 50],
            ['name' => 'Segun Afolabi',      'position' => 'MID', 'number' => 8,  'fantasy_price' => 65],
            ['name' => 'Chisom Ihejirika',   'position' => 'MID', 'number' => 10, 'fantasy_price' => 70],
            ['name' => 'Tunde Balogun',      'position' => 'FWD', 'number' => 9,  'fantasy_price' => 80],
            ['name' => 'Emeka Obi',          'position' => 'FWD', 'number' => 11, 'fantasy_price' => 75],
        ]);

        // EEE United — 8 players: 1 GK, 3 DEF, 2 MID, 2 FWD
        $this->seedPlayers($teamB->id, [
            ['name' => 'Biodun Adeyemi',     'position' => 'GK',  'number' => 1,  'fantasy_price' => 55],
            ['name' => 'Rotimi Olabode',     'position' => 'DEF', 'number' => 3,  'fantasy_price' => 50],
            ['name' => 'Nnamdi Okeke',       'position' => 'DEF', 'number' => 4,  'fantasy_price' => 50],
            ['name' => 'Damilola Adesanya',  'position' => 'DEF', 'number' => 5,  'fantasy_price' => 45],
            ['name' => 'Oluwaseun Falade',   'position' => 'MID', 'number' => 6,  'fantasy_price' => 65],
            ['name' => 'Chidi Okonkwo',      'position' => 'MID', 'number' => 8,  'fantasy_price' => 60],
            ['name' => 'Ayodeji Babatunde',  'position' => 'FWD', 'number' => 7,  'fantasy_price' => 70],
            ['name' => 'Ifeanyi Egwuatu',    'position' => 'FWD', 'number' => 9,  'fantasy_price' => 85],
        ]);

        // MED Stars — 8 players: 1 GK, 2 DEF, 3 MID, 2 FWD
        $this->seedPlayers($teamC->id, [
            ['name' => 'Uchenna Achike',     'position' => 'GK',  'number' => 1,  'fantasy_price' => 55],
            ['name' => 'Kayode Adeyinka',    'position' => 'DEF', 'number' => 2,  'fantasy_price' => 50],
            ['name' => 'Obinna Onyekwere',   'position' => 'DEF', 'number' => 5,  'fantasy_price' => 50],
            ['name' => 'Femi Ogunsanya',     'position' => 'MID', 'number' => 6,  'fantasy_price' => 60],
            ['name' => 'Abdullahi Musa',     'position' => 'MID', 'number' => 8,  'fantasy_price' => 65],
            ['name' => 'Taiwo Arowolo',      'position' => 'MID', 'number' => 10, 'fantasy_price' => 75],
            ['name' => 'Chibuzor Nwachukwu', 'position' => 'FWD', 'number' => 9,  'fantasy_price' => 80],
            ['name' => 'Samuel Adebisi',     'position' => 'FWD', 'number' => 11, 'fantasy_price' => 70],
        ]);

        // ─── Fixtures ─────────────────────────────────────────────────────────────
        $fixtures = [
            // Matchday 1
            ['home' => $teamA, 'away' => $teamB, 'matchday' => 1, 'days' => 7],
            ['home' => $teamC, 'away' => $teamA, 'matchday' => 1, 'days' => 7],
            // Matchday 2
            ['home' => $teamB, 'away' => $teamC, 'matchday' => 2, 'days' => 14],
            ['home' => $teamA, 'away' => $teamC, 'matchday' => 2, 'days' => 14],
            // Matchday 3
            ['home' => $teamB, 'away' => $teamA, 'matchday' => 3, 'days' => 21],
            ['home' => $teamC, 'away' => $teamB, 'matchday' => 3, 'days' => 21],
        ];

        foreach ($fixtures as $f) {
            Fixture::updateOrCreate(
                [
                    'tournament_id' => $tournament->id,
                    'home_team_id'  => $f['home']->id,
                    'away_team_id'  => $f['away']->id,
                    'matchday'      => $f['matchday'],
                ],
                [
                    'date'   => now()->addDays($f['days'])->setTime(15, 0),
                    'status' => 'scheduled',
                ]
            );
        }

        $this->command->info('✅  DevelopmentSeeder complete.');
        $this->command->info('    Tournament : Faculty Cup 2025/2026 (active)');
        $this->command->info('    Teams      : CSC FC, EEE United, MED Stars');
        $this->command->info('    Players    : 24 (8 per team)');
        $this->command->info('    Fixtures   : 6 scheduled across 3 matchdays');
    }

    private function seedPlayers(int $teamId, array $players): void
    {
        foreach ($players as $p) {
            Player::updateOrCreate(
                ['team_id' => $teamId, 'name' => $p['name']],
                [
                    'position'      => $p['position'],
                    'number'        => $p['number'],
                    'fantasy_price' => $p['fantasy_price'],
                    'goals'         => 0,
                    'assists'       => 0,
                    'yellow_cards'  => 0,
                    'red_cards'     => 0,
                ]
            );
        }
    }
}
