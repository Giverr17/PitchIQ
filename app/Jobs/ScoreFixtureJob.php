<?php

namespace App\Jobs;

use App\Models\Fixture;
use App\Models\FantasyPick;
use App\Models\FantasyTeam;
use App\Models\FixturePlayerStat;
use App\Models\PlayerEvent;
use App\Enums\PlayerEventType;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use App\Models\Prediction;
use App\Enums\PredictionType;
use App\Enums\MatchResult;
use Carbon\Carbon;
use App\Events\LeaderboardUpdated;

class ScoreFixtureJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $fixtureId)
    {
    }
    private function verifyPredictions(Fixture $fixture, $eventsByPlayer): void
    {
        // Determine the actual match result
        $home = $fixture->home_score ?? 0;
        $away = $fixture->away_score ?? 0;
        $actualResult = $home > $away ? 'home' : ($home < $away ? 'away' : 'draw');

        // Find the first goalscorer (earliest goal event by minute)
        $firstScorerId = PlayerEvent::where('fixture_id', $fixture->id)
            ->where('event_type', 'goal')
            ->orderByRaw('COALESCE(minute, 999)') // nulls last
            ->orderBy('id')                        // tiebreak by log order
            ->value('player_id');

        // Which teams kept a clean sheet
        $homeCleanSheet = $away === 0;
        $awayCleanSheet = $home === 0;

        // Players who got carded (yellow or red)
        $cardedPlayerIds = PlayerEvent::where('fixture_id', $fixture->id)
            ->whereIn('event_type', ['yellow', 'red'])
            ->pluck('player_id')
            ->unique()
            ->toArray();

        // Process every prediction for this fixture (re-score on correction too)
        $predictions = Prediction::where('fixture_id', $fixture->id)->get();

        foreach ($predictions as $prediction) {
            $points = 0;

            switch ($prediction->type) {

                case PredictionType::Result:
                    if ($prediction->predicted_result?->value === $actualResult) {
                        $points = 3;
                    }
                    break;

                case PredictionType::ExactScore:
                    if (
                        (int) $prediction->predicted_home_score === $home
                        && (int) $prediction->predicted_away_score === $away
                    ) {
                        $points = 5;
                    }
                    break;

                case PredictionType::FirstGoalscorer:
                    if ($firstScorerId && (int) $prediction->predicted_scorer_id === (int) $firstScorerId) {
                        $points = 4;
                    }
                    break;

                case PredictionType::CleanSheet:
                    $predictedTeam = (int) $prediction->predicted_team_id;
                    $kept = ($predictedTeam === $fixture->home_team_id && $homeCleanSheet)
                        || ($predictedTeam === $fixture->away_team_id && $awayCleanSheet);
                    if ($kept) {
                        $points = 3;
                    }
                    break;

                case PredictionType::CardedPlayer:
                    if (in_array((int) $prediction->predicted_scorer_id, $cardedPlayerIds)) {
                        $points = 2;
                    }
                    break;
            }

            // Stamp result — points (0 if wrong) and verified timestamp
            $prediction->update([
                'points_earned' => $points,
                'verified_at' => Carbon::now(),
            ]);
        }
    }
    public function handle(): void
    {
        $fixture = Fixture::with(['homeTeam.players', 'awayTeam.players'])->find($this->fixtureId);
        if (!$fixture) {
            return;
        }

        // ─── 1. Gather match data ────────────────────────────────────────────
        // All events for this fixture, grouped by player
        $eventsByPlayer = PlayerEvent::where('fixture_id', $this->fixtureId)
            ->get()
            ->groupBy('player_id');

        // All minutes/saves rows, keyed by player
        $statsByPlayer = FixturePlayerStat::where('fixture_id', $this->fixtureId)
            ->get()
            ->keyBy('player_id');

        // Which team did each player belong to? (id => 'home'|'away')
        $playerSide = [];
        foreach ($fixture->homeTeam->players as $p) {
            $playerSide[$p->id] = ['side' => 'home', 'position' => $p->position->value];
        }
        foreach ($fixture->awayTeam->players as $p) {
            $playerSide[$p->id] = ['side' => 'away', 'position' => $p->position->value];
        }

        // Did each side keep a clean sheet? (opponent scored 0)
        $homeCleanSheet = ($fixture->away_score ?? 0) === 0;
        $awayCleanSheet = ($fixture->home_score ?? 0) === 0;

        // ─── 2. Compute each player's raw match points ───────────────────────
        // playerId => points
        $playerPoints = [];

        foreach ($statsByPlayer as $playerId => $stat) {
            // Player must have featured
            if ($stat->minutes_played <= 0) {
                continue;
            }

            $info = $playerSide[$playerId] ?? null;
            if (!$info) {
                continue; // player not in either squad (shouldn't happen)
            }

            $position = $info['position'];
            $points = 0;

            // (a) Event points — goals adjusted by position, others flat
            foreach ($eventsByPlayer->get($playerId, collect()) as $event) {
                if ($event->event_type === \App\Enums\PlayerEventType::Goal) {
                    // Position-based goal points (simplified FPL): GK/DEF 6, MID 5, FWD 4
                    $points += match ($position) {
                        'GK', 'DEF' => 6,
                        'MID' => 5,
                        'FWD' => 4,
                        default => 5,
                    };
                } else {
                    $points += $event->event_type->fantasyPoints();
                }
            }

            // (b) Appearance points
            if ($stat->minutes_played >= 90) {
                $points += 2;
            } elseif ($stat->minutes_played >= 60) {
                $points += 1;
            }

            // (c) GK saves — 1pt per 3 saves
            if ($position === 'GK' && $stat->saves > 0) {
                $points += intdiv($stat->saves, 3);
            }
            $points += $stat->bonus;

            // (d) Clean sheet — needs 60+ minutes AND team conceded 0
            $teamCleanSheet = $info['side'] === 'home' ? $homeCleanSheet : $awayCleanSheet;
            if ($teamCleanSheet && $stat->minutes_played >= 60) {
                $points += match ($position) {
                    'GK', 'DEF' => 4,
                    'MID' => 1,
                    default => 0, // FWD gets nothing
                };
            }

            $playerPoints[$playerId] = $points;
        }

        // ─── 3. Verify predictions first so their points are ready ─────────
        $this->verifyPredictions($fixture, $eventsByPlayer);

        // ─── 4. Apply points to fantasy picks + roll up team totals ──────────
        DB::transaction(function () use ($fixture, $playerPoints) {

            // Find all picks for this fixture across all fantasy teams
            $picks = FantasyPick::where('fixture_id', $fixture->id)->get();

            // Group picks by their fantasy team so we can total each team
            $teamTotals = []; // fantasyTeamId => running total

            foreach ($picks as $pick) {
                $raw = $playerPoints[$pick->player_id] ?? 0;

                // Captain gets doubled points stored directly on the pick
                $final = $pick->is_captain ? $raw * 2 : $raw;

                $pick->update(['points_scored' => $final]);

                $teamTotals[$pick->fantasy_team_id] =
                    ($teamTotals[$pick->fantasy_team_id] ?? 0) + $final;
            }

            // Update each team's total_points from the sum of its own matchday picks
            foreach ($teamTotals as $teamId => $matchdayPoints) {
                $team = FantasyTeam::find($teamId);
                if ($team) {
                    $team->update([
                        'total_points' => FantasyPick::where('fantasy_team_id', $teamId)
                            ->where('fixture_id', $fixture->id)
                            ->sum('points_scored'),
                    ]);
                }
            }
        });
        // ─── 5. Tell all open leaderboards to refresh ────────────────────────
        broadcast(new LeaderboardUpdated());
    }


}