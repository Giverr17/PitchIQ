<?php

namespace Database\Seeders;

use App\Models\Fixture;
use App\Models\Player;
use App\Models\Team;
use App\Models\Tournament;
use Illuminate\Database\Seeder;

/**
 * Standalone 5-a-side competition: tournament + teams + players + fixtures.
 *
 * Fully self-contained and idempotent — keyed firstOrCreate everywhere, and
 * players are only seeded when a team is short. It never reads or touches any
 * other tournament, and creates no users or fantasy squads.
 */
class FiveASideTournamentSeeder extends Seeder
{
    private array $firstNames = [
        'Chidi', 'Emeka', 'Tunde', 'Kola', 'Seun', 'Bayo', 'Eze', 'Nkem',
        'Uche', 'Dele', 'Femi', 'Gbenga', 'Hakeem', 'Idris', 'Jide', 'Kunle',
        'Musa', 'Nosa', 'Obinna', 'Rotimi', 'Sola', 'Tobi', 'Victor', 'Yemi',
    ];

    private array $lastNames = [
        'Okafor', 'Bello', 'Adeyemi', 'Nwosu', 'Ibrahim', 'Okonkwo', 'Chukwu',
        'Obi', 'Lawal', 'Ogundipe', 'Nwachukwu', 'Aliyu', 'Danladi', 'Hassan',
        'Jimoh', 'Kehinde', 'Mohammed', 'Osagie', 'Adebayo', 'Taiwo', 'Yakubu',
        'Olawale', 'Babatunde', 'Uchenna',
    ];

    public function run(): void
    {
        // ══════════════════════════════════════════════════════════════════════
        // 1 — Tournament (keyed on name so re-runs don't duplicate)
        // ══════════════════════════════════════════════════════════════════════

        $tournament = Tournament::firstOrCreate(
            ['name' => 'Campus 5s Cup'],
            [
                'type'            => 'friendly',
                'season'          => '2025/2026',
                'status'          => 'active',
                'active_matchday' => 1,
                'squad_size'      => 5,
                'start_date'      => now(),
            ]
        );

        $this->command->info(
            ($tournament->wasRecentlyCreated ? 'Created' : 'Found')
            . " tournament: \"{$tournament->name}\" (ID {$tournament->id}, 5-a-side, status {$tournament->status->value})"
        );

        // ══════════════════════════════════════════════════════════════════════
        // 2 — Teams (keyed on tournament_id + name)
        // ══════════════════════════════════════════════════════════════════════

        $teamDefs = [
            ['name' => 'Alpha FC',     'colour' => '#00E676', 'faculty' => 'Engineering', 'department' => 'Computer'],
            ['name' => 'Bravo United', 'colour' => '#3B82F6', 'faculty' => 'Engineering', 'department' => 'Electrical'],
            ['name' => 'Charlie City', 'colour' => '#F59E0B', 'faculty' => 'Sciences',    'department' => 'Physics'],
            ['name' => 'Delta Stars',  'colour' => '#EF4444', 'faculty' => 'Sciences',    'department' => 'Chemistry'],
        ];

        $teams = [];
        $teamsCreated = 0;
        foreach ($teamDefs as $def) {
            $team = Team::firstOrCreate(
                ['tournament_id' => $tournament->id, 'name' => $def['name']],
                [
                    'faculty'    => $def['faculty'],
                    'department' => $def['department'],
                    'colour'     => $def['colour'],
                    'logo'       => null,
                ]
            );
            $teams[$def['name']] = $team;
            if ($team->wasRecentlyCreated) {
                $teamsCreated++;
            }
        }

        $this->command->info("Teams: {$teamsCreated} created, " . (count($teamDefs) - $teamsCreated) . ' already existed.');

        // ══════════════════════════════════════════════════════════════════════
        // 3 — Players (12 per team: 2 GK, 4 DEF, 3 MID, 3 FWD). Skip if team
        //     already has >= 8 players, so re-running is safe.
        // ══════════════════════════════════════════════════════════════════════

        $positions = array_merge(
            array_fill(0, 2, 'GK'),
            array_fill(0, 4, 'DEF'),
            array_fill(0, 3, 'MID'),
            array_fill(0, 3, 'FWD'),
        ); // 12 entries

        $playersCreated = 0;
        foreach ($teams as $team) {
            $existing = $team->players()->count();
            if ($existing >= 8) {
                $this->command->info("  [skip]   {$team->name} — already has {$existing} players.");
                continue;
            }

            $usedNames = [];
            foreach ($positions as $i => $position) {
                $name = $this->uniqueName($usedNames);
                $usedNames[] = $name;

                Player::create([
                    'team_id'       => $team->id,
                    'name'          => $name,
                    'position'      => $position,
                    'number'        => $i + 1,
                    'fantasy_price' => $this->priceFor($position),
                    'goals'         => 0,
                    'assists'       => 0,
                    'yellow_cards'  => 0,
                    'red_cards'     => 0,
                ]);
                $playersCreated++;
            }

            $this->command->info("  [seeded] {$team->name} — 12 players added.");
        }

        $this->command->info("Players: {$playersCreated} created.");

        // ══════════════════════════════════════════════════════════════════════
        // 4 — Fixtures (keyed on tournament_id + home + away + matchday)
        // ══════════════════════════════════════════════════════════════════════

        $fixtureDefs = [
            // Matchday 1
            ['home' => 'Alpha FC',     'away' => 'Bravo United', 'matchday' => 1, 'days' => 3],
            ['home' => 'Charlie City', 'away' => 'Delta Stars',  'matchday' => 1, 'days' => 3],
            // Matchday 2 (for testing matchday advance)
            ['home' => 'Alpha FC',     'away' => 'Charlie City', 'matchday' => 2, 'days' => 10],
            ['home' => 'Bravo United', 'away' => 'Delta Stars',  'matchday' => 2, 'days' => 10],
        ];

        $fixturesCreated = 0;
        foreach ($fixtureDefs as $def) {
            $home = $teams[$def['home']];
            $away = $teams[$def['away']];

            $fixture = Fixture::firstOrCreate(
                [
                    'tournament_id' => $tournament->id,
                    'home_team_id'  => $home->id,
                    'away_team_id'  => $away->id,
                    'matchday'      => $def['matchday'],
                ],
                [
                    'status'     => 'scheduled',
                    'home_score' => null,
                    'away_score' => null,
                    'date'       => now()->addDays($def['days'])->setTime(16, 0),
                ]
            );

            if ($fixture->wasRecentlyCreated) {
                $fixturesCreated++;
            }
        }

        $this->command->info("Fixtures: {$fixturesCreated} created, " . (count($fixtureDefs) - $fixturesCreated) . ' already existed.');
        $this->command->info('Done — "Campus 5s Cup" is ready.');
    }

    // ── Helpers ─────────────────────────────────────────────────────────────────

    private function priceFor(string $position): int
    {
        return match ($position) {
            'GK'  => rand(40, 60),
            'DEF' => rand(45, 65),
            'MID' => rand(55, 80),
            'FWD' => rand(60, 90),
        };
    }

    /** A first+last combination not already used within the same team. */
    private function uniqueName(array $used): string
    {
        $attempts = 0;
        do {
            $name = $this->firstNames[array_rand($this->firstNames)]
                . ' ' . $this->lastNames[array_rand($this->lastNames)];
            $attempts++;
        } while (in_array($name, $used, true) && $attempts < 200);

        return $name;
    }
}
