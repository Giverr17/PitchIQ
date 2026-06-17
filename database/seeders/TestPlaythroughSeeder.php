<?php

namespace Database\Seeders;

use App\Models\FantasyPick;
use App\Models\FantasyTeam;
use App\Models\Fixture;
use App\Models\Player;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestPlaythroughSeeder extends Seeder
{
    public function run(): void
    {
        // ══════════════════════════════════════════════════════════════════════
        // STEP 1 — Find the active tournament & read its squad size
        // ══════════════════════════════════════════════════════════════════════

        $tournament = Tournament::where('status', 'active')->first();
        if (!$tournament) {
            $this->command->error('No active tournament found. Aborting.');
            return;
        }

        // Read squad_size from the tournament — never hardcode it, so this same
        // seeder keeps working if the active tournament uses a different size.
        $squadSize = (int) ($tournament->squad_size ?? 0);

        $config = $this->squadConfig($squadSize);
        if (!$config) {
            $this->command->error(
                "Tournament \"{$tournament->name}\" has squad_size={$squadSize}, "
                . 'which this seeder has no formation profile for (supported: 5, 11). Aborting.'
            );
            return;
        }

        $this->command->info(
            "Active tournament: \"{$tournament->name}\" (ID {$tournament->id}, "
            . "matchday {$tournament->active_matchday}, squad_size {$squadSize})"
        );
        $this->command->line("Profile: formation {$config['formation']}, budget ₦{$config['budget']}, max {$config['maxPerTeam']}/team");
        $this->command->line('');

        // ══════════════════════════════════════════════════════════════════════
        // STEP 2 — Ensure 5 test users exist (players are NOT seeded; they exist)
        // ══════════════════════════════════════════════════════════════════════

        $this->command->line('── Ensuring test users ─────────────────────────────────────────');

        $userDefs = [
            ['name' => 'Tunde Adesanya', 'faculty' => 'Engineering'],
            ['name' => 'Chioma Okafor',  'faculty' => 'Science'],
            ['name' => 'Emeka Bello',    'faculty' => 'Law'],
            ['name' => 'Sola Adeyemi',   'faculty' => 'Arts'],
            ['name' => 'Musa Ibrahim',   'faculty' => 'Management'],
        ];

        $users = [];
        $usersEnsured = 0;
        foreach ($userDefs as $i => $def) {
            $n = $i + 1;
            [$wasCreated, $user] = $this->firstOrCreateUser("test{$n}@pitchiq.com", $def);
            $users[] = $user;
            $usersEnsured++;
            $label = $wasCreated ? 'created' : 'exists ';
            $this->command->info("  [{$label}] {$user->name} <test{$n}@pitchiq.com>");
        }
        $this->command->line('');

        // ══════════════════════════════════════════════════════════════════════
        // STEP 3 — Pick the first open fixture on the active matchday
        // ══════════════════════════════════════════════════════════════════════

        $this->command->line('── Building squads ─────────────────────────────────────────────');

        $openFixtures = Fixture::where('tournament_id', $tournament->id)
            ->where('status', 'scheduled')
            ->where('matchday', $tournament->active_matchday)
            ->orderBy('date')
            ->get();

        if ($openFixtures->isEmpty()) {
            $this->command->warn('No scheduled fixtures on the active matchday. Skipping squad creation.');
            return;
        }

        $fixture  = $openFixtures->first();
        $homeTeam = Team::find($fixture->home_team_id);
        $awayTeam = Team::find($fixture->away_team_id);

        $this->command->info(
            "Using fixture ID {$fixture->id}: "
            . ($homeTeam?->name ?? '?') . ' vs ' . ($awayTeam?->name ?? '?')
            . " (MD{$fixture->matchday})"
        );

        // Pool = both teams' players, cheapest first so per-position picking stays in budget.
        $pool = Player::whereIn('team_id', [$fixture->home_team_id, $fixture->away_team_id])
            ->orderBy('fantasy_price')
            ->get();

        if ($pool->count() < $squadSize) {
            $this->command->error(
                "Only {$pool->count()} players available for this fixture — need at least "
                . "{$squadSize}. Aborting squad creation."
            );
            return;
        }

        // ══════════════════════════════════════════════════════════════════════
        // STEP 4 — Build one legal squad per user
        // ══════════════════════════════════════════════════════════════════════

        $squadsCreated = 0;

        foreach ($users as $user) {
            $squad = $this->buildSquad($pool, $config['needs'], $config['budget'], $config['maxPerTeam']);

            if ($squad === null) {
                $this->command->warn("  [skip]  {$user->name} — could not build a legal squad (budget/positions/pool).");
                continue;
            }

            $totalCost       = collect($squad)->sum('fantasy_price');
            $budgetRemaining = $config['budget'] - $totalCost;
            $firstName       = explode(' ', $user->name)[0];

            $fantasyTeam = FantasyTeam::updateOrCreate(
                [
                    'user_id'    => $user->id,
                    'fixture_id' => $fixture->id,
                ],
                [
                    'tournament_id'    => $tournament->id,
                    'team_name'        => "{$firstName}'s {$squadSize}",
                    'formation'        => $config['formation'],
                    'budget_remaining' => $budgetRemaining,
                    'total_points'     => 0,
                ]
            );

            // Clear existing picks so a re-run rebuilds cleanly (idempotent).
            FantasyPick::where('fantasy_team_id', $fantasyTeam->id)->delete();

            foreach ($squad as $idx => $player) {
                FantasyPick::create([
                    'fantasy_team_id' => $fantasyTeam->id,
                    'fixture_id'      => $fixture->id,
                    'player_id'       => $player->id,
                    'matchday'        => $fixture->matchday,
                    'is_captain'      => $idx === 0,   // first pick captains
                    'is_vice_captain' => $idx === 1,   // second pick is vice
                    'points_scored'   => 0,
                ]);
            }

            $squadsCreated++;
            $this->command->info(
                "  [squad] {$user->name} → {$firstName}'s {$squadSize} | "
                . "cost ₦{$totalCost} | remaining ₦{$budgetRemaining}"
            );
        }

        // ══════════════════════════════════════════════════════════════════════
        // STEP 5 — Summary
        // ══════════════════════════════════════════════════════════════════════

        $this->command->line('');
        $this->command->info('── Summary ─────────────────────────────────────────────────────');
        $this->command->info("  squad_size (from tournament) : {$squadSize}  → formation {$config['formation']}");
        $this->command->info("  users ensured                : {$usersEnsured}");
        $this->command->info("  squads created               : {$squadsCreated} / " . count($users));
        $this->command->info("  fixture used                 : ID {$fixture->id} ("
            . ($homeTeam?->name ?? '?') . ' vs ' . ($awayTeam?->name ?? '?') . ", MD{$fixture->matchday})");
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Formation profile for a given squad size. Returns null for unsupported
     * sizes so the caller can abort with a clear message. The needs array sums
     * to the squad size; budget + maxPerTeam scale with the format.
     */
    private function squadConfig(int $size): ?array
    {
        return match ($size) {
            5 => [
                'formation'  => '1-2-1',
                'needs'      => ['GK' => 1, 'DEF' => 1, 'MID' => 2, 'FWD' => 1],
                'budget'     => 320,
                'maxPerTeam' => 3,
            ],
            11 => [
                'formation'  => '4-3-3',
                'needs'      => ['GK' => 1, 'DEF' => 4, 'MID' => 3, 'FWD' => 3],
                'budget'     => 700,
                'maxPerTeam' => 7,
            ],
            default => null,
        };
    }

    /**
     * Build a legal squad from $pool that satisfies $needs (per-position counts),
     * stays within $budget, and takes at most $maxPerTeam from any single team.
     * Picks cheapest-first per position. Returns the chosen Player models in
     * pick order, or null if a legal squad can't be formed.
     */
    private function buildSquad($pool, array $needs, int $budget, int $maxPerTeam): ?array
    {
        $squad     = [];
        $pickedIds = [];
        $teamCount = [];
        $remaining = $budget;

        foreach ($needs as $pos => $required) {
            $filled = 0;

            foreach ($pool as $player) {
                if ($filled >= $required) break;
                if ($player->position->value !== $pos) continue;
                if (in_array($player->id, $pickedIds, true)) continue;
                if ($player->fantasy_price > $remaining) continue;

                $tid = $player->team_id;
                if (($teamCount[$tid] ?? 0) >= $maxPerTeam) continue;

                $squad[]         = $player;
                $pickedIds[]     = $player->id;
                $remaining      -= $player->fantasy_price;
                $teamCount[$tid] = ($teamCount[$tid] ?? 0) + 1;
                $filled++;
            }

            if ($filled < $required) {
                return null; // not enough affordable players in this position
            }
        }

        return $squad;
    }

    /** Return [bool $wasCreated, User $user]. */
    private function firstOrCreateUser(string $email, array $def): array
    {
        $existing = User::where('email', $email)->first();
        if ($existing) {
            return [false, $existing];
        }

        $user = User::create([
            'name'     => $def['name'],
            'email'    => $email,
            'password' => Hash::make('password'),
            'faculty'  => $def['faculty'],
            'tokens'   => 150,
            'phone'    => '080' . str_pad((string) rand(0, 99999999), 8, '0', STR_PAD_LEFT),
            'is_admin' => false,
        ]);

        return [true, $user];
    }
}
