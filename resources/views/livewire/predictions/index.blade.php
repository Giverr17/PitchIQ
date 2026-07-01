<?php

use App\Models\FantasyTeam;
use App\Models\Fixture;
use App\Models\Player;
use App\Models\Prediction;
use App\Models\Tournament;
use App\Models\TournamentPrediction;
use App\Models\TokenCost;
use App\Models\TokenTransaction;
use App\Enums\PredictionType;
use App\Enums\TournamentPredictionType;
use App\Enums\TokenTransactionType;
use App\Enums\MatchResult;
use App\Enums\FixtureStatus;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component {
    // ─── Page State ────────────────────────────────────────────────────────────
    public string $activeTab = 'fixtures';   // 'fixtures' | 'tournament' | 'my_picks'
    public string $toast = '';
    public string $toastType = 'success';
    public int $userTokens = 0;
    public int $predictionCost = 1;
    public int $editFixtureId = 0;       // fixture being edited via ?fixture_id= param
    public array $editingFixtures = [];      // IDs of fixtures being re-edited (no token charge)

    // ─── Fixture Predictions State ─────────────────────────────────────────────
    // keyed by fixture_id → [ 'result' => 'home'|'draw'|'away', 'home_score' => int, ... ]
    public array $picks = [];

    // ─── Tournament Predictions State ──────────────────────────────────────────
    // keyed by tournament_id → [ 'top_scorer' => player_id, 'most_assists' => player_id ]
    public array $tournamentPicks = [];

    // ─── Data ──────────────────────────────────────────────────────────────────
    public $fixtures;
    public $tournaments;
    public array $tournamentPlayers = [];   // [tournament_id => [ ['id','name'], ... ]]
    public array $myPicks = [];
    public array $mySquads = [];

    public function mount(): void
    {
        $user = Auth::user();
        $this->userTokens = $user->tokens;
        $this->predictionCost = TokenCost::costFor(TokenCost::PREDICTION);

        // Load only fixtures in the active matchday of their tournament (mirrors squad-builder logic)
        $allScheduled = Fixture::with(['homeTeam.players', 'awayTeam.players', 'tournament'])
            ->where('status', FixtureStatus::Scheduled)
            ->whereHas(
                'tournament',
                fn($q) => $q
                    ->where('status', \App\Enums\TournamentStatus::Active)
                    ->whereColumn('fixtures.matchday', 'tournaments.active_matchday')
            )
            ->orderBy('date')
            ->get();

        $predictedIds = Prediction::where('user_id', $user->id)
            ->whereIn('fixture_id', $allScheduled->pluck('id'))
            ->pluck('fixture_id')
            ->unique();

        $this->fixtures = $allScheduled->whereNotIn('id', $predictedIds)->values();

        // Initialize a full-key slot for every unpredicted fixture
        foreach ($this->fixtures as $fixture) {
            $this->picks[$fixture->id] = $this->defaultPicks();
        }

        // Load active/upcoming tournaments for tournament-scope predictions
        $this->tournaments = Tournament::whereIn('status', [\App\Enums\TournamentStatus::Upcoming, \App\Enums\TournamentStatus::Active])->get();

        // Players for the tournament-prediction dropdowns, scoped to each
        // tournament's OWN teams (player → team → tournament) so you only pick
        // from players actually competing in that tournament.
        $this->tournamentPlayers = Player::query()
            ->join('teams', 'players.team_id', '=', 'teams.id')
            ->whereIn('teams.tournament_id', $this->tournaments->pluck('id'))
            ->orderBy('players.name')
            ->get(['players.id', 'players.name', 'teams.tournament_id'])
            ->groupBy('tournament_id')
            ->map(fn($group) => $group->map(fn($p) => ['id' => $p->id, 'name' => $p->name])->values()->toArray())
            ->toArray();

        // Initialize a full-key slot for every tournament
        foreach ($this->tournaments as $tournament) {
            $this->tournamentPicks[$tournament->id] = ['top_scorer' => null, 'most_assists' => null];
        }

        // Pre-populate tournament picks from DB
        $existingTournPreds = TournamentPrediction::where('user_id', $user->id)
            ->whereIn('tournament_id', $this->tournaments->pluck('id'))
            ->get();

        foreach ($existingTournPreds as $tp) {
            $tid = $tp->tournament_id;
            $this->tournamentPicks[$tid][$tp->type->value] = $tp->predicted_player_id;
        }

        $this->loadMyPicks();
        $this->loadMySquads();

        // ── Direct link: ?fixture_id=X (from My Picks "Edit Picks" button) ──────
        $paramFid = (int) request()->query('fixture_id', 0);
        if ($paramFid) {
            $editFixture = $allScheduled->firstWhere('id', $paramFid);
            if ($editFixture) {
                $this->editFixtureId = $paramFid;
                $this->activeTab = 'fixtures';

                // Ensure the fixture is in the list (may have been excluded as already-predicted)
                if (!$this->fixtures->contains('id', $paramFid)) {
                    $this->fixtures = collect([$editFixture])->concat($this->fixtures);
                }

                // Initialise pick slot and pre-populate from DB
                $this->picks[$paramFid] = $this->defaultPicks();
                $existing = Prediction::where('user_id', $user->id)
                    ->where('fixture_id', $paramFid)
                    ->get();

                if ($existing->isNotEmpty()) {
                    $this->editingFixtures[] = $paramFid;
                    foreach ($existing as $ep) {
                        if ($ep->type === PredictionType::Result) {
                            $this->picks[$paramFid]['result'] = $ep->predicted_result?->value;
                        } elseif ($ep->type === PredictionType::ExactScore) {
                            $this->picks[$paramFid]['home_score'] = $ep->predicted_home_score;
                            $this->picks[$paramFid]['away_score'] = $ep->predicted_away_score;
                        } elseif ($ep->type === PredictionType::FirstGoalscorer) {
                            $this->picks[$paramFid]['scorer_id'] = $ep->predicted_scorer_id;
                        } elseif ($ep->type === PredictionType::CleanSheet) {
                            $this->picks[$paramFid]['clean_sheet_team'] = $ep->predicted_team_id;
                        } elseif ($ep->type === PredictionType::CardedPlayer) {
                            $this->picks[$paramFid]['carded_id'] = $ep->predicted_scorer_id;
                        }
                    }
                }
            }
        }
    }

    // ─── My Picks (submitted predictions) ─────────────────────────────────────

    public function refreshMyPicks(): void
    {
        $this->loadMyPicks();
    }

    // ─── My Squads (fantasy pitches, independent of predictions) ───────────────

    public function refreshMySquads(): void
    {
        $this->loadMySquads();
    }

    private function loadMySquads(): void
    {
        $user = Auth::user();

        $this->mySquads = FantasyTeam::with(['fantasyPicks.player.team'])
            ->where('user_id', $user->id)
            ->get()
            ->map(function ($ft) {
                $fixture = Fixture::with(['homeTeam', 'awayTeam'])->find($ft->fixture_id);
                if (!$fixture)
                    return null;

                $picks = $ft->fantasyPicks->map(fn($pick) => [
                    'player_name' => $pick->player?->name ?? '?',
                    'position' => $pick->player?->position?->value ?? 'FWD',
                    'team_colour' => $pick->player?->team?->colour ?? '#00E676',
                    'is_captain' => $pick->is_captain,
                    'is_vice_captain' => $pick->is_vice_captain,
                    'points_scored' => $pick->points_scored,
                ])->toArray();

                return [
                    'fixture_id' => $fixture->id,
                    'matchday' => $fixture->matchday,
                    'date_label' => $fixture->date?->format('d M · H:i'),
                    'status' => $fixture->status->value,
                    'status_label' => $fixture->status->label(),
                    'status_badge' => $fixture->status->badgeClass(),
                    'home_team_name' => $fixture->homeTeam?->name ?? 'Home',
                    'away_team_name' => $fixture->awayTeam?->name ?? 'Away',
                    'home_team_colour' => $fixture->homeTeam?->colour ?? '#00E676',
                    'away_team_colour' => $fixture->awayTeam?->colour ?? '#3B82F6',
                    'home_score' => $fixture->home_score,
                    'away_score' => $fixture->away_score,
                    'team_name' => $ft->team_name,
                    'formation' => $ft->formation ?? '4-3-3',
                    'total_points' => $ft->total_points,
                    'picks' => $picks,
                    'is_scheduled' => $fixture->status === FixtureStatus::Scheduled,
                ];
            })
            ->filter()
            ->sortByDesc(fn($s) => $s['matchday'])
            ->values()
            ->toArray();
    }

    private function loadMyPicks(): void
    {
        $user = Auth::user();

        $predictedFixtureIds = Prediction::where('user_id', $user->id)
            ->pluck('fixture_id')
            ->unique()
            ->values();

        if ($predictedFixtureIds->isEmpty()) {
            $this->myPicks = [];
            return;
        }

        $this->myPicks = Fixture::with(['homeTeam', 'awayTeam', 'tournament'])
            ->whereIn('id', $predictedFixtureIds)
            ->orderByRaw("CASE WHEN status = 'live' THEN 0 WHEN status = 'scheduled' THEN 1 ELSE 2 END")
            ->orderByDesc('date')
            ->get()
            ->map(function ($fixture) use ($user) {
                $predictions = Prediction::with(['predictedScorer', 'predictedTeam'])
                    ->where('user_id', $user->id)
                    ->where('fixture_id', $fixture->id)
                    ->get()
                    ->map(fn($p) => [
                        'type_label' => $p->type->label(),
                        'type_icon' => $p->type->icon(),
                        'value' => $this->predDisplayValue($p),
                        'points_earned' => $p->points_earned,
                        'is_verified' => $p->verified_at !== null,
                    ])
                    ->toArray();

                $fantasyTeam = FantasyTeam::with(['fantasyPicks.player.team'])
                    ->where('user_id', $user->id)
                    ->where('fixture_id', $fixture->id)
                    ->first();

                $squad = null;
                if ($fantasyTeam) {
                    $picks = $fantasyTeam->fantasyPicks->map(fn($pick) => [
                        'player_name' => $pick->player?->name ?? '?',
                        'position' => $pick->player?->position?->value ?? 'FWD',
                        'team_colour' => $pick->player?->team?->colour ?? '#00E676',
                        'is_captain' => $pick->is_captain,
                        'is_vice_captain' => $pick->is_vice_captain,
                        'points_scored' => $pick->points_scored,
                    ])->toArray();

                    $squad = [
                        'team_name' => $fantasyTeam->team_name,
                        'formation' => $fantasyTeam->formation ?? $this->inferFormation($picks),
                        'total_points' => $fantasyTeam->total_points,
                        'picks' => $picks,
                    ];
                }

                return [
                    'id' => $fixture->id,
                    'matchday' => $fixture->matchday,
                    'date_label' => $fixture->date?->format('d M · H:i'),
                    'status' => $fixture->status->value,
                    'status_label' => $fixture->status->label(),
                    'status_badge' => $fixture->status->badgeClass(),
                    'is_scheduled' => $fixture->status === FixtureStatus::Scheduled,
                    'home_team_name' => $fixture->homeTeam?->name ?? 'Home',
                    'away_team_name' => $fixture->awayTeam?->name ?? 'Away',
                    'home_team_colour' => $fixture->homeTeam?->colour ?? '#00E676',
                    'away_team_colour' => $fixture->awayTeam?->colour ?? '#3B82F6',
                    'home_score' => $fixture->home_score,
                    'away_score' => $fixture->away_score,
                    'tournament_name' => $fixture->tournament?->name ?? '',
                    'predictions' => $predictions,
                    'pred_points' => collect($predictions)->sum('points_earned'),
                    'squad' => $squad,
                ];
            })
            ->toArray();
    }

    private function predDisplayValue(Prediction $p): string
    {
        return match ($p->type) {
            PredictionType::Result => $p->predicted_result?->label() ?? '—',
            PredictionType::ExactScore => ($p->predicted_home_score ?? '?') . ' – ' . ($p->predicted_away_score ?? '?'),
            PredictionType::FirstGoalscorer => $p->predictedScorer?->name ?? '—',
            PredictionType::CleanSheet => $p->predictedTeam?->name ?? '—',
            PredictionType::CardedPlayer => $p->predictedScorer?->name ?? '—',
        };
    }

    private function inferFormation(array $picks): string
    {
        $c = collect($picks);
        return $c->where('position', 'DEF')->count()
            . '-' . $c->where('position', 'MID')->count()
            . '-' . $c->where('position', 'FWD')->count();
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    private function defaultPicks(): array
    {
        return [
            'result' => null,
            'home_score' => null,
            'away_score' => null,
            'scorer_id' => null,
            'clean_sheet_team' => null,
            'carded_id' => null,
        ];
    }

    // ─── Actions ───────────────────────────────────────────────────────────────
    public function suggestPrediction(int $fixtureId, \App\Services\AiService $ai): void
    {
        $fixture = Fixture::with(['homeTeam.players', 'awayTeam.players'])->find($fixtureId);
        if (!$fixture) {
            $this->toast = 'Fixture not found.';
            $this->toastType = 'error';
            return;
        }

        $homePlayers = $fixture->homeTeam->players->map(fn($p) => ['id' => $p->id, 'name' => $p->name])->toArray();
        $awayPlayers = $fixture->awayTeam->players->map(fn($p) => ['id' => $p->id, 'name' => $p->name])->toArray();

        $result = $ai->suggestPrediction(
            $fixture->homeTeam->name,
            $fixture->awayTeam->name,
            $homePlayers,
            $awayPlayers,
            $fixture->home_team_id,
            $fixture->away_team_id
        );

        if (!$result['success']) {
            $this->toast = $result['message'] . ' You can still pick yourself.';
            $this->toastType = 'error';
            return;
        }

        // Fill this fixture's pick slot (user can change anything before saving)
        $this->picks[$fixtureId] = array_merge($this->defaultPicks(), $result['prediction']);

        $this->toast = 'AI suggestion filled in — tweak it however you like, then save.';
        $this->toastType = 'success';
    }
    public function saveFixturePredictions(int $fixtureId): void
    {
        $user = Auth::user();
        $fixture = Fixture::find($fixtureId);
        $isEdit = in_array($fixtureId, $this->editingFixtures);

        if (!$fixture || !$fixture->isPredictable()) {
            $this->toast = 'This fixture is locked and cannot be predicted.';
            $this->toastType = 'error';
            return;
        }

        if (!$isEdit && $this->userTokens < $this->predictionCost) {
            $this->toast = "You need at least {$this->predictionCost} token(s) to submit predictions.";
            $this->toastType = 'error';
            return;
        }

        // On edit: wipe existing predictions so we can re-save clean
        if ($isEdit) {
            Prediction::where('user_id', $user->id)
                ->where('fixture_id', $fixtureId)
                ->delete();
        }

        $picks = array_merge($this->defaultPicks(), $this->picks[$fixtureId] ?? []);
        $saved = 0;

        // Result
        if ($picks['result'] !== null) {
            Prediction::create([
                'user_id' => $user->id,
                'fixture_id' => $fixtureId,
                'type' => PredictionType::Result,
                'predicted_result' => MatchResult::tryFrom($picks['result']),
            ]);
            $saved++;
        }

        // Exact Score
        if ($picks['home_score'] !== null && $picks['away_score'] !== null) {
            Prediction::create([
                'user_id' => $user->id,
                'fixture_id' => $fixtureId,
                'type' => PredictionType::ExactScore,
                'predicted_home_score' => $picks['home_score'],
                'predicted_away_score' => $picks['away_score'],
            ]);
            $saved++;
        }

        // First Goalscorer
        if ($picks['scorer_id']) {
            Prediction::create([
                'user_id' => $user->id,
                'fixture_id' => $fixtureId,
                'type' => PredictionType::FirstGoalscorer,
                'predicted_scorer_id' => $picks['scorer_id'],
            ]);
            $saved++;
        }

        // Clean Sheet
        if ($picks['clean_sheet_team']) {
            Prediction::create([
                'user_id' => $user->id,
                'fixture_id' => $fixtureId,
                'type' => PredictionType::CleanSheet,
                'predicted_team_id' => $picks['clean_sheet_team'],
            ]);
            $saved++;
        }

        // Carded Player
        if ($picks['carded_id']) {
            Prediction::create([
                'user_id' => $user->id,
                'fixture_id' => $fixtureId,
                'type' => PredictionType::CardedPlayer,
                'predicted_scorer_id' => $picks['carded_id'],
            ]);
            $saved++;
        }

        if ($saved > 0) {
            $this->fixtures = $this->fixtures->reject(fn($f) => $f->id === $fixtureId)->values();
            unset($this->picks[$fixtureId]);
            $this->editingFixtures = array_values(array_diff($this->editingFixtures, [$fixtureId]));

            if ($isEdit) {
                $this->loadMyPicks();
                $this->activeTab = 'my_picks';
                $this->toast = 'Predictions updated!';
            } else {
                $user->decrement('tokens', $this->predictionCost);
                $this->userTokens -= $this->predictionCost;

                TokenTransaction::create([
                    'user_id' => $user->id,
                    'type' => TokenTransactionType::Spent,
                    'amount' => -$this->predictionCost,
                    'description' => 'Fixture prediction submitted',
                ]);

                $this->loadMyPicks();
                $cost = $this->predictionCost;
                $this->toast = "Predictions saved! {$cost} token(s) spent. {$this->userTokens} remaining.";
            }
            $this->toastType = 'success';
        } else {
            $this->toast = 'Make at least one prediction before saving.';
            $this->toastType = 'error';
        }
    }

    public function saveTournamentPredictions(int $tournamentId): void
    {
        $user = Auth::user();
        $tournament = Tournament::find($tournamentId);

        if (!$tournament || !$tournament->isPredictable()) {
            $this->toast = 'This tournament is locked for predictions.';
            $this->toastType = 'error';
            return;
        }

        $picks = array_merge(['top_scorer' => null, 'most_assists' => null], $this->tournamentPicks[$tournamentId] ?? []);
        $saved = 0;

        if (!empty($picks['top_scorer'])) {
            TournamentPrediction::updateOrCreate(
                ['user_id' => $user->id, 'tournament_id' => $tournamentId, 'type' => TournamentPredictionType::TopScorer],
                ['predicted_player_id' => $picks['top_scorer']]
            );
            $saved++;
        }

        if (!empty($picks['most_assists'])) {
            TournamentPrediction::updateOrCreate(
                ['user_id' => $user->id, 'tournament_id' => $tournamentId, 'type' => TournamentPredictionType::MostAssists],
                ['predicted_player_id' => $picks['most_assists']]
            );
            $saved++;
        }

        if ($saved > 0) {
            $this->toast = "🏆 Tournament prediction(s) saved!";
            $this->toastType = 'success';
        } else {
            $this->toast = 'Select at least one player to save.';
            $this->toastType = 'error';
        }
    }

    public function dismissToast(): void
    {
        $this->toast = '';
    }
}
?>

@section('title', 'Match Predictions — PitchIQ')
@section('meta_description', 'Make your match predictions on PitchIQ. Predict results, exact scores, goalscorers, clean sheets and more to earn tokens.')

<div class="max-w-5xl mx-auto px-5 sm:px-8 py-10"
    x-data="{ toast: @entangle('toast'), toastType: @entangle('toastType') }">

    {{-- ── Back to previous page ──────────────────────────────────────────────── --}}
    <button type="button" onclick="window.history.back()"
        class="inline-flex items-center gap-1.5 font-mono text-[11px] text-on-surface-variant/60 hover:text-[#00E676] transition-colors cursor-pointer mb-6">
        <span class="material-symbols-outlined text-[16px]">arrow_back</span>
        Back
    </button>

    {{-- ── Toast Notification ─────────────────────────────────────────────────── --}}
    <div x-show="toast !== ''" x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-[-12px]" x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        x-init="$watch('toast', v => { if(v) setTimeout(() => { $wire.dismissToast() }, 4000) })"
        class="fixed z-[60] inset-x-4 bottom-4 sm:inset-x-auto sm:bottom-auto sm:top-20 sm:right-4 sm:max-w-sm">
        <div :class="toastType === 'success'
                ? 'bg-surface-container border-primary-container/40 text-primary-container'
                : 'bg-surface-container border-error/40 text-error'"
            class="flex items-center gap-3 px-5 py-3.5 rounded-2xl border shadow-2xl font-mono text-xs font-semibold">
            <span class="material-symbols-outlined text-[18px]"
                x-text="toastType === 'success' ? 'check_circle' : 'error'"></span>
            <span x-text="toast" class="flex-1"></span>
            <button wire:click="dismissToast"
                class="opacity-60 hover:opacity-100 cursor-pointer transition-opacity">&times;</button>
        </div>
    </div>

    {{-- ── Page Header ──────────────────────────────────────────────────────────── --}}
    <div class="mb-8">
        <div class="flex items-center gap-3 mb-2">
            <div
                class="w-10 h-10 rounded-xl bg-primary-container/15 border border-primary-container/30 flex items-center justify-center">
                <span class="material-symbols-outlined text-primary-container text-[22px]">sports_score</span>
            </div>
            <div>
                <h1 class="font-display font-black text-2xl sm:text-3xl text-on-surface uppercase tracking-tight">
                    Match <span class="text-gradient">Predictions</span>
                </h1>
                <p class="font-mono text-xs text-on-surface-variant/60">Make your calls. Earn tokens. Rule the campus.
                </p>
            </div>
        </div>

        {{-- Token balance pill --}}
        <div class="mt-4 flex flex-wrap items-center gap-3">
            <div
                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-surface-container border border-outline-variant/20">
                <span class="text-lg">🪙</span>
                <span class="font-mono text-sm font-bold text-primary-container">{{ $userTokens }}</span>
                <span class="font-mono text-xs text-on-surface-variant/60">tokens available</span>
            </div>
            @if($predictionCost > 0)
                <div
                    class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-surface-container/60 border border-outline-variant/15">
                    <span class="material-symbols-outlined text-[13px] text-on-surface-variant/50">toll</span>
                    <span class="font-mono text-xs text-on-surface-variant/60">{{ $predictionCost }}
                        token{{ $predictionCost !== 1 ? 's' : '' }} per prediction</span>
                </div>
            @endif
        </div>
    </div>

    {{-- ── Tab Switcher ─────────────────────────────────────────────────────────── --}}
    <div class="flex gap-2 mb-6 p-1 rounded-xl bg-surface-container border border-outline-variant/15 w-fit flex-wrap">
        <button wire:click="$set('activeTab', 'fixtures')" id="tab-fixtures"
            class="px-5 py-2 rounded-lg font-mono text-xs font-bold uppercase tracking-wider transition-all duration-200 cursor-pointer
                   {{ $activeTab === 'fixtures' ? 'bg-primary-container text-background shadow-lg shadow-primary-container/20' : 'text-on-surface-variant hover:text-on-surface' }}">
            <span class="material-symbols-outlined text-[14px] align-middle mr-1">calendar_month</span>
            Fixtures
        </button>
        <button wire:click="$set('activeTab', 'tournament')" id="tab-tournament"
            class="px-5 py-2 rounded-lg font-mono text-xs font-bold uppercase tracking-wider transition-all duration-200 cursor-pointer
                   {{ $activeTab === 'tournament' ? 'bg-secondary-container text-background shadow-lg shadow-secondary-container/20' : 'text-on-surface-variant hover:text-on-surface' }}">
            <span class="material-symbols-outlined text-[14px] align-middle mr-1">emoji_events</span>
            Tournament
        </button>
        <button wire:click="$set('activeTab', 'my_picks')" id="tab-my-picks"
            class="px-5 py-2 rounded-lg font-mono text-xs font-bold uppercase tracking-wider transition-all duration-200 cursor-pointer relative
                   {{ $activeTab === 'my_picks' ? 'text-background shadow-lg' : 'text-on-surface-variant hover:text-on-surface' }}"
            style="{{ $activeTab === 'my_picks' ? 'background: linear-gradient(135deg, #00E676 0%, #00b359 100%);' : '' }}">
            <span class="material-symbols-outlined text-[14px] align-middle mr-1">fact_check</span>
            My Picks
            @if(count($myPicks) > 0)
                <span
                    class="ml-1 inline-flex items-center justify-center w-4 h-4 rounded-full text-[8px] font-black
                                     {{ $activeTab === 'my_picks' ? 'bg-black/20 text-background' : 'bg-primary-container text-background' }}">
                    {{ count($myPicks) }}
                </span>
            @endif
        </button>
        <button wire:click="$set('activeTab', 'my_squad')" id="tab-my-squad"
            class="px-5 py-2 rounded-lg font-mono text-xs font-bold uppercase tracking-wider transition-all duration-200 cursor-pointer relative
                   {{ $activeTab === 'my_squad' ? 'text-background shadow-lg' : 'text-on-surface-variant hover:text-on-surface' }}"
            style="{{ $activeTab === 'my_squad' ? 'background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);' : '' }}">
            <span class="material-symbols-outlined text-[14px] align-middle mr-1">groups</span>
            My Squad
            @if(count($mySquads) > 0)
                <span
                    class="ml-1 inline-flex items-center justify-center w-4 h-4 rounded-full text-[8px] font-black
                                     {{ $activeTab === 'my_squad' ? 'bg-black/20 text-background' : 'bg-blue-500 text-white' }}">
                    {{ count($mySquads) }}
                </span>
            @endif
        </button>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════════ --}}
    {{-- ── FIXTURE PREDICTIONS TAB ─────────────────────────────────────────────── --}}
    {{-- ══════════════════════════════════════════════════════════════════════════ --}}
    @if($activeTab === 'fixtures')

        @forelse($fixtures as $fixture)
            @php
                $fid = $fixture->id;
                $pick = array_merge(['result' => null, 'home_score' => null, 'away_score' => null, 'scorer_id' => null, 'clean_sheet_team' => null, 'carded_id' => null], $picks[$fid] ?? []);
                $isLocked = !$fixture->isPredictable();
                $homeTeam = $fixture->homeTeam;
                $awayTeam = $fixture->awayTeam;
                $homePlayers = $homeTeam?->players->sortBy('name') ?? collect();
                $awayPlayers = $awayTeam?->players->sortBy('name') ?? collect();
            @endphp

            <div id="fixture-{{ $fid }}" wire:key="fixture-{{ $fid }}"
                class="neo-surface rounded-2xl border overflow-hidden mb-5 scroll-mt-24
                                        {{ $fid === $editFixtureId ? 'border-amber-500/40' : 'border-outline-variant/15' }}
                                        {{ $isLocked ? 'opacity-60' : '' }}"
                @if($fid === $editFixtureId) x-data
                x-init="$nextTick(() => setTimeout(() => $el.scrollIntoView({ behavior: 'smooth', block: 'start' }), 250))" @endif>

                {{-- Edit mode indicator --}}
                @if($fid === $editFixtureId && in_array($fid, $editingFixtures))
                    <div class="px-5 py-2.5 flex items-center gap-2 border-b border-amber-500/20"
                        style="background:rgba(245,158,11,0.08);">
                        <span class="material-symbols-outlined text-[14px] text-amber-400">edit_note</span>
                        <span class="font-mono text-[10px] text-amber-400 uppercase tracking-wider">Editing your prediction — no
                            additional tokens will be charged</span>
                    </div>
                @endif

                {{-- Fixture Header --}}
                <div class="px-5 py-4 border-b border-outline-variant/10 bg-surface-container/30">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="flex items-center gap-4">
                            {{-- Matchday badge --}}
                            <div
                                class="flex-shrink-0 px-3 py-1 rounded-lg bg-primary-container/10 border border-primary-container/20">
                                <span class="font-mono text-[10px] font-bold text-primary-container uppercase tracking-widest">
                                    MD{{ $fixture->matchday }}
                                </span>
                            </div>

                            {{-- Teams --}}
                            <div class="flex items-center gap-2 font-display font-black text-sm sm:text-base text-on-surface">
                                <span>{{ $homeTeam?->name ?? 'Home' }}</span>
                                <span class="text-on-surface-variant/40 font-sans font-normal text-xs">vs</span>
                                <span>{{ $awayTeam?->name ?? 'Away' }}</span>
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            {{-- Date --}}
                            @if($fixture->date)
                                <span class="font-mono text-[11px] text-on-surface-variant/60">
                                    {{ $fixture->date->format('d M · H:i') }}
                                </span>
                            @endif

                            {{-- Status badge --}}
                            <span class="px-2.5 py-1 rounded-full font-mono text-[10px] font-bold uppercase tracking-wider
                                                {{ $fixture->status->badgeClass() }}">
                                {{ $fixture->status->label() }}
                            </span>
                        </div>
                    </div>

                    {{-- Tournament name --}}
                    @if($fixture->tournament)
                        <p class="mt-1.5 font-mono text-[10px] text-on-surface-variant/40 uppercase tracking-wider">
                            {{ $fixture->tournament->name }} · {{ $fixture->tournament->season }}
                        </p>
                    @endif
                </div>

                {{-- AI Suggest --}}
                @if(!$isLocked)
                    <div class="px-5 py-3 border-b border-outline-variant/10 flex items-center justify-between gap-3"
                        style="background:rgba(0,230,118,0.02);">
                        <span class="font-mono text-[10px] text-on-surface-variant/50">
                            Not sure? Let AI fill a starting prediction — you can change it.
                        </span>
                        <button wire:click="suggestPrediction({{ $fid }})" wire:loading.attr="disabled"
                            wire:target="suggestPrediction({{ $fid }})"
                            class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg font-mono text-[10px] font-bold uppercase tracking-wider border border-[#00E676]/40 text-[#00E676] hover:bg-[#00E676]/10 transition-all cursor-pointer disabled:opacity-50 flex-shrink-0">
                            <span wire:loading.remove wire:target="suggestPrediction({{ $fid }})">✨ Suggest</span>
                            <span wire:loading wire:target="suggestPrediction({{ $fid }})">Thinking…</span>
                        </button>
                    </div>
                @endif

                {{-- Prediction Rows --}}
                <div class="divide-y divide-outline-variant/8">

                    {{-- 1. Match Result ──────────────────────────────────────────── --}}
                    <div class="px-5 py-4 flex jflex-col sm:flex-row sm:items-center gap-3">
                        <div class="flex items-center gap-2 w-36 flex-shrink-0">
                            <span class="material-symbols-outlined text-[16px] text-secondary-container">scoreboard</span>
                            <span
                                class="font-mono text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Result</span>
                        </div>
                        <div class="flex gap-2 flex-wrap">
                            @foreach(['home' => $homeTeam?->name ?? 'Home', 'draw' => 'Draw', 'away' => $awayTeam?->name ?? 'Away'] as $val => $label)
                                    <button @if(!$isLocked) wire:click="$set('picks.{{ $fid }}.result', '{{ $val }}')" @endif
                                        id="result-{{ $fid }}-{{ $val }}"
                                        class="px-4 py-2 rounded-xl font-mono text-xs font-bold uppercase tracking-wider border transition-all duration-150
                                                                           {{ !$isLocked ? 'cursor-pointer hover:scale-[1.02] active:scale-[0.98]' : 'cursor-not-allowed' }}
                                                                           {{ ($pick['result'] ?? null) === $val
                                ? 'bg-primary-container text-background border-primary-container shadow-lg shadow-primary-container/20'
                                : 'bg-surface-container border-outline-variant/30 text-on-surface-variant hover:border-primary-container/40 hover:text-on-surface' }}">
                                        {{ $label }}
                                    </button>
                            @endforeach
                        </div>
                        @if(($pick['result'] ?? null))
                            <span class="font-mono text-[10px] text-primary-container flex items-center gap-1 ml-auto">
                                <span class="material-symbols-outlined text-[12px]">check_circle</span> Picked
                            </span>
                        @endif
                    </div>

                    {{-- 2. Exact Score ─────────────────────────────────────────────── --}}
                    <div class="px-5 py-4 flex flex-col sm:flex-row sm:items-center gap-3">
                        <div class="flex items-center gap-2 w-36 flex-shrink-0">
                            <span class="material-symbols-outlined text-[16px] text-secondary-container">123</span>
                            <span class="font-mono text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Exact
                                Score</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <input type="number" min="0" max="20" wire:model="picks.{{ $fid }}.home_score"
                                id="score-home-{{ $fid }}" @if($isLocked) disabled @endif placeholder="0"
                                class="w-16 text-center px-2 py-2 rounded-xl border font-mono text-sm font-bold
                                                       bg-surface-container border-outline-variant/30 text-on-surface
                                                       focus:outline-none focus:border-primary-container/60 focus:bg-surface-container-high
                                                       {{ $isLocked ? 'opacity-50 cursor-not-allowed' : '' }}
                                                       transition-colors [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none">
                            <span class="font-mono text-lg font-black text-on-surface-variant/30">—</span>
                            <input type="number" min="0" max="20" wire:model="picks.{{ $fid }}.away_score"
                                id="score-away-{{ $fid }}" @if($isLocked) disabled @endif placeholder="0"
                                class="w-16 text-center px-2 py-2 rounded-xl border font-mono text-sm font-bold
                                                       bg-surface-container border-outline-variant/30 text-on-surface
                                                       focus:outline-none focus:border-primary-container/60 focus:bg-surface-container-high
                                                       {{ $isLocked ? 'opacity-50 cursor-not-allowed' : '' }}
                                                       transition-colors [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none">
                        </div>
                        @if(($pick['home_score'] ?? null) !== null && ($pick['away_score'] ?? null) !== null)
                            <span class="font-mono text-[10px] text-primary-container flex items-center gap-1 ml-auto">
                                <span class="material-symbols-outlined text-[12px]">check_circle</span> Picked
                            </span>
                        @endif
                    </div>

                    {{-- 3. First Goalscorer ────────────────────────────────────────── --}}
                    <div class="px-5 py-4 flex flex-col sm:flex-row sm:items-center gap-3">
                        <div class="flex items-center gap-2 w-36 flex-shrink-0">
                            <span class="material-symbols-outlined text-[16px] text-secondary-container">sports_soccer</span>
                            <span class="font-mono text-xs font-semibold text-on-surface-variant uppercase tracking-wider">1st
                                Scorer</span>
                        </div>
                        <select wire:model="picks.{{ $fid }}.scorer_id" id="scorer-{{ $fid }}" @if($isLocked) disabled @endif
                            class="flex-1 max-w-xs px-3 py-2 rounded-xl border font-mono text-xs
                                                   bg-surface-container border-outline-variant/30 text-on-surface
                                                   focus:outline-none focus:border-primary-container/60
                                                   {{ $isLocked ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer' }}
                                                   transition-colors">
                            <option value="">— Pick a player —</option>
                            <optgroup label="{{ $homeTeam?->name ?? 'Home' }}">
                                @foreach($homePlayers as $player)
                                    <option value="{{ $player->id }}">{{ $player->name }}</option>
                                @endforeach
                            </optgroup>
                            <optgroup label="{{ $awayTeam?->name ?? 'Away' }}">
                                @foreach($awayPlayers as $player)
                                    <option value="{{ $player->id }}">{{ $player->name }}</option>
                                @endforeach
                            </optgroup>
                        </select>
                        @if($pick['scorer_id'] ?? null)
                            <span class="font-mono text-[10px] text-primary-container flex items-center gap-1">
                                <span class="material-symbols-outlined text-[12px]">check_circle</span> Picked
                            </span>
                        @endif
                    </div>

                    {{-- 4. Clean Sheet ──────────────────────────────────────────────── --}}
                    <div class="px-5 py-4 flex flex-col sm:flex-row sm:items-center gap-3">
                        <div class="flex items-center gap-2 w-36 flex-shrink-0">
                            <span class="material-symbols-outlined text-[16px] text-secondary-container">shield</span>
                            <span class="font-mono text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Clean
                                Sheet</span>
                        </div>
                        <div class="flex gap-2">
                            @foreach(['none' => 'Neither', $homeTeam?->id => $homeTeam?->name ?? 'Home', $awayTeam?->id => $awayTeam?->name ?? 'Away'] as $val => $label)
                                @if($val !== null)
                                    <button @if(!$isLocked)
                                        wire:click="$set('picks.{{ $fid }}.clean_sheet_team', '{{ $val === 'none' ? '' : $val }}')"
                                    @endif id="cs-{{ $fid }}-{{ $val }}"
                                        class="px-4 py-2 rounded-xl font-mono text-xs font-semibold border transition-all duration-150
                                                                           {{ !$isLocked ? 'cursor-pointer hover:scale-[1.02]' : 'cursor-not-allowed' }}
                                                                           {{ (string) ($pick['clean_sheet_team'] ?? null) === (string) ($val === 'none' ? '' : $val)
                                    ? 'bg-primary-container/20 text-primary-container border-primary-container/40'
                                    : 'bg-surface-container border-outline-variant/30 text-on-surface-variant hover:border-primary-container/30' }}">
                                        {{ $label }}
                                    </button>
                                @endif
                            @endforeach
                        </div>
                    </div>

                    {{-- 5. Player to be Carded ─────────────────────────────────────── --}}
                    <div class="px-5 py-4 flex flex-col sm:flex-row sm:items-center gap-3">
                        <div class="flex items-center gap-2 w-36 flex-shrink-0">
                            <span class="material-symbols-outlined text-[16px] text-secondary-container">style</span>
                            <span
                                class="font-mono text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Carded</span>
                        </div>
                        <select wire:model="picks.{{ $fid }}.carded_id" id="carded-{{ $fid }}" @if($isLocked) disabled @endif
                            class="flex-1 max-w-xs px-3 py-2 rounded-xl border font-mono text-xs
                                                   bg-surface-container border-outline-variant/30 text-on-surface
                                                   focus:outline-none focus:border-primary-container/60
                                                   {{ $isLocked ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer' }}
                                                   transition-colors">
                            <option value="">— Pick a player —</option>
                            <optgroup label="{{ $homeTeam?->name ?? 'Home' }}">
                                @foreach($homePlayers as $player)
                                    <option value="{{ $player->id }}">{{ $player->name }}</option>
                                @endforeach
                            </optgroup>
                            <optgroup label="{{ $awayTeam?->name ?? 'Away' }}">
                                @foreach($awayPlayers as $player)
                                    <option value="{{ $player->id }}">{{ $player->name }}</option>
                                @endforeach
                            </optgroup>
                        </select>
                        @if($pick['carded_id'] ?? null)
                            <span class="font-mono text-[10px] text-primary-container flex items-center gap-1">
                                <span class="material-symbols-outlined text-[12px]">check_circle</span> Picked
                            </span>
                        @endif
                    </div>
                </div>

                {{-- Save Button --}}
                <div
                    class="px-5 py-4 bg-surface-container/20 border-t border-outline-variant/10 flex items-center justify-between gap-4">
                    @if(in_array($fid, $editingFixtures))
                        <p class="font-mono text-[10px] flex items-center gap-1" style="color:#00E676;">
                            <span class="material-symbols-outlined text-[12px]">token</span>
                            No additional tokens charged
                        </p>
                    @else
                        <p class="font-mono text-[10px] text-on-surface-variant/40">
                            {{ $isLocked ? '🔒 Locked — fixture in progress or completed' : 'Predictions lock when fixture goes live' }}
                        </p>
                    @endif
                    @if(!$isLocked)
                        <button wire:click="saveFixturePredictions({{ $fid }})" wire:loading.attr="disabled"
                            id="save-fixture-{{ $fid }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-mono text-xs font-bold uppercase tracking-wider
                                                           bg-primary-container text-background hover:bg-primary-fixed transition-all duration-200
                                                           hover:scale-[1.02] active:scale-[0.98] shadow-lg shadow-primary-container/20
                                                           disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer">
                            <span class="material-symbols-outlined text-[16px]" wire:loading.class="animate-spin"
                                wire:loading.class.remove="text-[16px]" wire:target="saveFixturePredictions({{ $fid }})">save</span>
                            <span wire:loading.remove
                                wire:target="saveFixturePredictions({{ $fid }})">{{ in_array($fid, $editingFixtures) ? 'Update Predictions' : 'Save Predictions' }}</span>
                            <span wire:loading wire:target="saveFixturePredictions({{ $fid }})">Saving…</span>
                        </button>
                    @endif
                </div>
            </div>

        @empty
            {{-- Empty state --}}
            <div class="neo-surface rounded-2xl border border-outline-variant/15 px-8 py-16 text-center">
                <div
                    class="w-16 h-16 mx-auto mb-5 rounded-2xl bg-surface-container border border-outline-variant/20 flex items-center justify-center">
                    <span class="material-symbols-outlined text-on-surface-variant/30 text-[32px]">calendar_month</span>
                </div>
                <h3 class="font-display font-black text-lg text-on-surface mb-2">No fixtures yet</h3>
                <p class="font-mono text-xs text-on-surface-variant/50">Fixtures will appear here once the admin schedules
                    upcoming matches.</p>
            </div>
        @endforelse

    @endif

    {{-- ══════════════════════════════════════════════════════════════════════════ --}}
    {{-- ── TOURNAMENT PREDICTIONS TAB ──────────────────────────────────────────── --}}
    {{-- ══════════════════════════════════════════════════════════════════════════ --}}
    @if($activeTab === 'tournament')

        {{-- Info banner --}}
        <div
            class="mb-6 flex items-start gap-3 p-4 rounded-2xl bg-secondary-container/10 border border-secondary-container/25">
            <span class="material-symbols-outlined text-secondary-container text-[20px] flex-shrink-0 mt-0.5">info</span>
            <p class="font-mono text-xs text-on-surface-variant/80 leading-relaxed">
                Tournament predictions cover the <strong class="text-on-surface">entire competition</strong> — pick the top
                scorer and the most assists provider for each active tournament. These can be updated while the tournament
                is active.
            </p>
        </div>

        @forelse($tournaments as $tournament)
            @php
                $tid = $tournament->id;
                $tpick = $tournamentPicks[$tid] ?? ['top_scorer' => null, 'most_assists' => null];
                $locked = !$tournament->isPredictable();
            @endphp

            <div class="neo-surface rounded-2xl border border-outline-variant/15 overflow-hidden mb-5"
                wire:key="tournament-{{ $tid }}">

                {{-- Tournament Header --}}
                <div class="px-5 py-4 border-b border-outline-variant/10 bg-surface-container/30">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div
                                class="w-9 h-9 rounded-xl bg-secondary-container/15 border border-secondary-container/25 flex items-center justify-center flex-shrink-0">
                                <span class="material-symbols-outlined text-secondary-container text-[18px]">emoji_events</span>
                            </div>
                            <div>
                                <h2 class="font-display font-black text-sm text-on-surface uppercase tracking-tight">
                                    {{ $tournament->name }}
                                </h2>
                                <p class="font-mono text-[10px] text-on-surface-variant/50">{{ $tournament->season }} ·
                                    {{ $tournament->type->label() }}
                                </p>
                            </div>
                        </div>
                        <span class="px-2.5 py-1 rounded-full font-mono text-[10px] font-bold uppercase tracking-wider
                                            {{ $tournament->status->badgeClass() }}">
                            {{ $tournament->status->label() }}
                        </span>
                    </div>
                </div>

                {{-- Prediction Inputs --}}
                <div class="divide-y divide-outline-variant/8">

                    {{-- Top Scorer --}}
                    <div class="px-5 py-4 flex flex-col sm:flex-row sm:items-center gap-3">
                        <div class="flex items-center gap-2 w-36 flex-shrink-0">
                            <span class="material-symbols-outlined text-[16px] text-secondary-container">military_tech</span>
                            <span class="font-mono text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Top
                                Scorer</span>
                        </div>
                        <select wire:model="tournamentPicks.{{ $tid }}.top_scorer" id="ts-{{ $tid }}" @if($locked) disabled
                        @endif class="flex-1 max-w-xs px-3 py-2 rounded-xl border font-mono text-xs
                                                   bg-surface-container border-outline-variant/30 text-on-surface
                                                   focus:outline-none focus:border-secondary-container/60
                                                   {{ $locked ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer' }}
                                                   transition-colors">
                            <option value="">— Pick a player —</option>
                            @foreach($tournamentPlayers[$tid] ?? [] as $player)
                                <option value="{{ $player['id'] }}">{{ $player['name'] }}</option>
                            @endforeach
                        </select>
                        @if($tpick['top_scorer'] ?? null)
                            <span class="font-mono text-[10px] text-secondary-container flex items-center gap-1">
                                <span class="material-symbols-outlined text-[12px]">check_circle</span> Picked
                            </span>
                        @endif
                    </div>

                    {{-- Most Assists --}}
                    <div class="px-5 py-4 flex flex-col sm:flex-row sm:items-center gap-3">
                        <div class="flex items-center gap-2 w-36 flex-shrink-0">
                            <span class="material-symbols-outlined text-[16px] text-secondary-container">handshake</span>
                            <span class="font-mono text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Most
                                Assists</span>
                        </div>
                        <select wire:model="tournamentPicks.{{ $tid }}.most_assists" id="ma-{{ $tid }}" @if($locked) disabled
                        @endif class="flex-1 max-w-xs px-3 py-2 rounded-xl border font-mono text-xs
                                                   bg-surface-container border-outline-variant/30 text-on-surface
                                                   focus:outline-none focus:border-secondary-container/60
                                                   {{ $locked ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer' }}
                                                   transition-colors">
                            <option value="">— Pick a player —</option>
                            @foreach($tournamentPlayers[$tid] ?? [] as $player)
                                <option value="{{ $player['id'] }}">{{ $player['name'] }}</option>
                            @endforeach
                        </select>
                        @if($tpick['most_assists'] ?? null)
                            <span class="font-mono text-[10px] text-secondary-container flex items-center gap-1">
                                <span class="material-symbols-outlined text-[12px]">check_circle</span> Picked
                            </span>
                        @endif
                    </div>
                </div>

                {{-- Save Button --}}
                <div
                    class="px-5 py-4 bg-surface-container/20 border-t border-outline-variant/10 flex items-center justify-between gap-4">
                    <p class="font-mono text-[10px] text-on-surface-variant/40">
                        {{ $locked ? '🔒 Tournament completed — predictions are locked' : 'You can update these while the tournament is active' }}
                    </p>
                    @if(!$locked)
                        <button wire:click="saveTournamentPredictions({{ $tid }})" wire:loading.attr="disabled"
                            id="save-tournament-{{ $tid }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-mono text-xs font-bold uppercase tracking-wider
                                                           bg-secondary-container text-background hover:bg-secondary-fixed transition-all duration-200
                                                           hover:scale-[1.02] active:scale-[0.98] shadow-lg shadow-secondary-container/20
                                                           disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer">
                            <span class="material-symbols-outlined text-[16px]" wire:loading.class="animate-spin"
                                wire:loading.class.remove="text-[16px]"
                                wire:target="saveTournamentPredictions({{ $tid }})">save</span>
                            <span wire:loading.remove wire:target="saveTournamentPredictions({{ $tid }})">Save Picks</span>
                            <span wire:loading wire:target="saveTournamentPredictions({{ $tid }})">Saving…</span>
                        </button>
                    @endif
                </div>
            </div>

        @empty
            <div class="neo-surface rounded-2xl border border-outline-variant/15 px-8 py-16 text-center">
                <div
                    class="w-16 h-16 mx-auto mb-5 rounded-2xl bg-surface-container border border-outline-variant/20 flex items-center justify-center">
                    <span class="material-symbols-outlined text-on-surface-variant/30 text-[32px]">emoji_events</span>
                </div>
                <h3 class="font-display font-black text-lg text-on-surface mb-2">No active tournaments</h3>
                <p class="font-mono text-xs text-on-surface-variant/50">Tournament-level predictions will appear here when a
                    tournament is active or upcoming.</p>
            </div>
        @endforelse

    @endif

    {{-- ══════════════════════════════════════════════════════════════════════════ --}}
    {{-- ── MY PICKS TAB ───────────────────────────────────────────────────────── --}}
    {{-- ══════════════════════════════════════════════════════════════════════════ --}}
    @if($activeTab === 'my_picks')
        {{-- Poll every 30 s while any fixture is not yet completed, so points update automatically --}}
        <div {!! collect($myPicks)->contains(fn($p) => $p['status'] !== 'completed') ? 'wire:poll.30s="refreshMyPicks"' : '' !!}>

            @if(empty($myPicks))
                <div class="neo-surface rounded-2xl border border-outline-variant/15 px-8 py-16 text-center">
                    <div
                        class="w-16 h-16 mx-auto mb-5 rounded-2xl bg-surface-container border border-outline-variant/20 flex items-center justify-center">
                        <span class="material-symbols-outlined text-on-surface-variant/30 text-[32px]">fact_check</span>
                    </div>
                    <h3 class="font-display font-black text-lg text-on-surface mb-2">No predictions yet</h3>
                    <p class="font-mono text-xs text-on-surface-variant/50 mb-5">Submit predictions on the Fixtures tab and
                        they'll appear here.</p>
                    <button wire:click="$set('activeTab', 'fixtures')"
                        class="px-5 py-2.5 rounded-xl font-mono text-xs font-bold uppercase tracking-wider text-background cursor-pointer"
                        style="background: linear-gradient(135deg, #00E676 0%, #00b359 100%);">
                        Go to Fixtures
                    </button>
                </div>
            @else

                @foreach($myPicks as $myPick)
                    <div wire:key="mypick-{{ $myPick['id'] }}"
                        class="neo-surface rounded-2xl border border-outline-variant/15 overflow-hidden mb-5">

                        {{-- ── Fixture Header ──────────────────────────────────────── --}}
                        <div class="px-5 py-4 border-b border-outline-variant/10 bg-surface-container/30">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div class="flex items-center gap-4">
                                    <div
                                        class="flex-shrink-0 px-3 py-1 rounded-lg bg-primary-container/10 border border-primary-container/20">
                                        <span
                                            class="font-mono text-[10px] font-bold text-primary-container uppercase tracking-widest">MD{{ $myPick['matchday'] }}</span>
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2 font-display font-black text-sm sm:text-base">
                                            <span
                                                style="color: {{ $myPick['home_team_colour'] }}">{{ $myPick['home_team_name'] }}</span>
                                            @if($myPick['status'] !== 'scheduled')
                                                <span class="font-mono font-black text-white px-2">
                                                    {{ $myPick['home_score'] ?? 0 }} – {{ $myPick['away_score'] ?? 0 }}
                                                </span>
                                            @else
                                                <span class="text-on-surface-variant/40 font-sans font-normal text-xs">vs</span>
                                            @endif
                                            <span
                                                style="color: {{ $myPick['away_team_colour'] }}">{{ $myPick['away_team_name'] }}</span>
                                        </div>
                                        @if($myPick['tournament_name'])
                                            <p class="font-mono text-[10px] text-on-surface-variant/40 mt-0.5 uppercase tracking-wider">
                                                {{ $myPick['tournament_name'] }}
                                                @if($myPick['date_label']) · {{ $myPick['date_label'] }} @endif
                                            </p>
                                        @endif
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 flex-shrink-0 flex-wrap">
                                    <span
                                        class="px-2.5 py-1 rounded-full font-mono text-[10px] font-bold uppercase tracking-wider {{ $myPick['status_badge'] }}">
                                        {{ $myPick['status_label'] }}
                                    </span>
                                    @if($myPick['is_scheduled'])
                                        <a href="{{ route('predictions.index') }}?fixture_id={{ $myPick['id'] }}"
                                            class="px-3 py-1.5 rounded-xl font-mono text-[10px] font-bold border border-outline-variant/25 text-on-surface-variant hover:border-amber-500/40 hover:text-amber-400 transition-all inline-flex items-center gap-1.5">
                                            <span class="material-symbols-outlined text-[12px]">edit_note</span>
                                            Edit Picks
                                        </a>
                                        <a href="{{ route('squad.builder') }}?fixture_id={{ $myPick['id'] }}"
                                            class="px-3 py-1.5 rounded-xl font-mono text-[10px] font-bold border border-outline-variant/25 text-on-surface-variant hover:border-primary-container/40 hover:text-primary-container transition-all inline-flex items-center gap-1.5">
                                            <span class="material-symbols-outlined text-[12px]">edit</span>
                                            Edit Squad
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- ── Predictions list (full width) ──────────────────────── --}}
                        <div class="p-5 space-y-1">
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="font-display font-black text-sm uppercase tracking-wider text-white">Your Predictions
                                </h4>
                                @if($myPick['pred_points'] > 0)
                                    <span class="font-mono text-xs font-bold px-2.5 py-1 rounded-full"
                                        style="background:rgba(0,230,118,0.15); color:#00E676; border:1px solid rgba(0,230,118,0.3);">
                                        +{{ $myPick['pred_points'] }} pts
                                    </span>
                                @endif
                            </div>

                            @foreach($myPick['predictions'] as $pred)
                                <div
                                    class="flex items-center gap-3 px-3 py-2.5 rounded-xl border border-outline-variant/15 bg-white/[0.02]">
                                    <span
                                        class="material-symbols-outlined text-[16px] text-on-surface-variant/50 flex-shrink-0">{{ $pred['type_icon'] }}</span>
                                    <div class="flex-1 min-w-0">
                                        <span
                                            class="block font-mono text-[10px] text-on-surface-variant/50 uppercase tracking-wider">{{ $pred['type_label'] }}</span>
                                        <span class="block font-bold text-sm text-white truncate">{{ $pred['value'] }}</span>
                                    </div>
                                    @if($pred['is_verified'])
                                        <span
                                            class="flex-shrink-0 font-mono text-xs font-black px-2 py-0.5 rounded-lg
                                                                                         {{ $pred['points_earned'] > 0 ? 'text-[#00E676]' : 'text-on-surface-variant/40' }}"
                                            style="{{ $pred['points_earned'] > 0 ? 'background:rgba(0,230,118,0.12);' : '' }}">
                                            {{ $pred['points_earned'] > 0 ? '+' . $pred['points_earned'] : '✗ 0' }}
                                        </span>
                                    @else
                                        <span class="flex-shrink-0 font-mono text-[10px] text-on-surface-variant/30 italic">pending</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                    </div>{{-- /fixture card --}}
                @endforeach

            @endif

        </div>{{-- /wire:poll wrapper --}}
    @endif

    {{-- ══════════════════════════════════════════════════════════════════════════ --}}
    {{-- ── MY SQUAD TAB ─────────────────────────────────────────────────────────── --}}
    {{-- ══════════════════════════════════════════════════════════════════════════ --}}
    @if($activeTab === 'my_squad')
        <div {!! collect($mySquads)->contains(fn($s) => $s['status'] !== 'completed') ? 'wire:poll.30s="refreshMySquads"' : '' !!}>

            @if(empty($mySquads))
                <div class="neo-surface rounded-2xl border border-outline-variant/15 px-8 py-16 text-center">
                    <div
                        class="w-16 h-16 mx-auto mb-5 rounded-2xl bg-surface-container border border-outline-variant/20 flex items-center justify-center">
                        <span class="material-symbols-outlined text-on-surface-variant/30 text-[32px]">groups</span>
                    </div>
                    <h3 class="font-display font-black text-lg text-on-surface mb-2">No squads yet</h3>
                    <p class="font-mono text-xs text-on-surface-variant/50 mb-5">Build a squad for an upcoming fixture and it
                        will appear here.</p>
                    <a href="{{ route('squad.builder') }}"
                        class="px-5 py-2.5 rounded-xl font-mono text-xs font-bold uppercase tracking-wider text-background inline-block"
                        style="background: linear-gradient(135deg, #00E676 0%, #00b359 100%);">
                        Build Squad
                    </a>
                </div>
            @else

                @foreach($mySquads as $sq)
                    @php
                        $fParts = explode('-', $sq['formation']);
                        $pitchRows = [
                            'GK' => 1,
                            'DEF' => (int) ($fParts[0] ?? 4),
                            'MID' => (int) ($fParts[1] ?? 3),
                            'FWD' => (int) ($fParts[2] ?? 3),
                        ];
                        $picksByPos = collect($sq['picks'])->groupBy('position');
                    @endphp

                    <div wire:key="mysquad-{{ $sq['fixture_id'] }}"
                        class="neo-surface rounded-2xl border border-outline-variant/15 overflow-hidden mb-5">

                        {{-- Header --}}
                        <div class="px-5 py-4 border-b border-outline-variant/10 bg-surface-container/30">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div class="flex items-center gap-4">
                                    <div
                                        class="flex-shrink-0 px-3 py-1 rounded-lg bg-primary-container/10 border border-primary-container/20">
                                        <span
                                            class="font-mono text-[10px] font-bold text-primary-container uppercase tracking-widest">MD{{ $sq['matchday'] }}</span>
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2 font-display font-black text-sm sm:text-base">
                                            <span style="color:{{ $sq['home_team_colour'] }}">{{ $sq['home_team_name'] }}</span>
                                            @if($sq['status'] !== 'scheduled')
                                                <span class="font-mono font-black text-white px-2">{{ $sq['home_score'] ?? 0 }} –
                                                    {{ $sq['away_score'] ?? 0 }}</span>
                                            @else
                                                <span class="text-on-surface-variant/40 font-sans font-normal text-xs">vs</span>
                                            @endif
                                            <span style="color:{{ $sq['away_team_colour'] }}">{{ $sq['away_team_name'] }}</span>
                                        </div>
                                        @if($sq['date_label'] ?? null)
                                            <p class="font-mono text-[10px] text-on-surface-variant/40 mt-0.5">{{ $sq['date_label'] }}
                                            </p>
                                        @endif
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 flex-wrap flex-shrink-0">
                                    <span
                                        class="px-2.5 py-1 rounded-full font-mono text-[10px] font-bold uppercase tracking-wider {{ $sq['status_badge'] }}">
                                        {{ $sq['status_label'] }}
                                    </span>
                                    @if($sq['is_scheduled'])
                                        <a href="{{ route('squad.builder') }}?fixture_id={{ $sq['fixture_id'] }}"
                                            class="px-3 py-1.5 rounded-xl font-mono text-[10px] font-bold border border-outline-variant/25 text-on-surface-variant hover:border-primary-container/40 hover:text-primary-container transition-all inline-flex items-center gap-1.5">
                                            <span class="material-symbols-outlined text-[12px]">edit</span>
                                            Edit Squad
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Pitch --}}
                        <div class="p-5">
                            <div class="flex items-center justify-between mb-3">
                                <div>
                                    <h4 class="font-display font-black text-sm uppercase tracking-wider text-white">
                                        {{ $sq['team_name'] }}
                                    </h4>
                                    <span class="font-mono text-[10px] text-on-surface-variant/50">{{ $sq['formation'] }}</span>
                                </div>
                                @if($sq['total_points'] > 0)
                                    <span class="font-mono text-sm font-black px-3 py-1 rounded-full"
                                        style="background:rgba(0,230,118,0.12); color:#00E676; border:1px solid rgba(0,230,118,0.25);">
                                        {{ $sq['total_points'] }} pts
                                    </span>
                                @endif
                            </div>

                            <div class="rounded-xl border border-[#00E676]/10 p-3 flex flex-col justify-between gap-3"
                                style="min-height:300px; background: repeating-linear-gradient(0deg, rgba(0,230,118,0.02) 0px, rgba(0,230,118,0.02) 32px, transparent 32px, transparent 64px);">
                                @foreach($pitchRows as $pos => $slotCount)
                                    @php $rowPlayers = $picksByPos[$pos] ?? collect(); @endphp
                                    @if($rowPlayers->isNotEmpty())
                                        <div class="flex justify-around items-end gap-1">
                                            @foreach($rowPlayers as $pl)
                                                <div class="text-center flex flex-col items-center" style="max-width:72px;">
                                                    <div class="relative w-11 h-11 rounded-full flex items-center justify-center font-black text-[10px] border-2"
                                                        style="background:{{ $pl['team_colour'] }}22; border-color:{{ $pl['team_colour'] }}; color:{{ $pl['team_colour'] }};">
                                                        {{ $pos }}
                                                        @if($pl['is_captain'])
                                                            <span
                                                                class="absolute -top-1 -right-1 w-4 h-4 rounded-full font-black text-[7px] flex items-center justify-center text-black"
                                                                style="background:#00E676; line-height:1;">C</span>
                                                        @elseif($pl['is_vice_captain'])
                                                            <span
                                                                class="absolute -top-1 -right-1 w-4 h-4 rounded-full font-black text-[7px] flex items-center justify-center text-white border border-white/40"
                                                                style="background:rgba(255,255,255,0.15); line-height:1;">V</span>
                                                        @endif
                                                    </div>
                                                    <span class="block text-[9px] font-bold text-white mt-0.5 leading-tight text-center"
                                                        style="max-width:68px; overflow:hidden; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical;">{{ $pl['player_name'] }}</span>
                                                    <span class="block font-mono text-[9px] font-black mt-0.5"
                                                        style="color:{{ $pl['points_scored'] > 0 ? '#00E676' : 'rgba(255,255,255,0.25)' }}">
                                                        {{ $pl['points_scored'] > 0 ? '+' . $pl['points_scored'] : '0' }}
                                                    </span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>

                    </div>{{-- /squad card --}}
                @endforeach

            @endif

        </div>{{-- /poll wrapper --}}
    @endif

</div>