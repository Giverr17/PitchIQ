<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Player;
use App\Models\Fixture;
use App\Models\Tournament;
use App\Models\FantasyTeam;
use App\Models\FantasyPick;
use App\Models\AppSetting;
use App\Models\TokenCost;
use App\Models\TokenTransaction;
use App\Enums\TokenTransactionType;
use App\Enums\FixtureStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;


new #[Layout('layouts.app')] class extends Component {

    // ─── Navigation step ─────────────────────────────────────────────
    public string $step = 'select_tournament';
    // ─── Tokens & budget ─────────────────────────────────────────────
    public int $userTokens = 0;
    public int $squadCost = 0;
    public int $budget = 1500;

    // ─── Tournament context ──────────────────────────────────────────
    public ?array $activeTournament = null;
    public array $tournamentList = [];

    // ─── Fixture selection ───────────────────────────────────────────
    public array $fixtureList = []; // all upcoming fixtures with squad status
    public ?int $selectedFixtureId = null;
    public array $selectedFixture = []; // flat fixture data for the build step

    // ─── Available players (scoped to selected fixture's two teams) ──
    public array $availablePlayers = [];

    // ─── Formation ───────────────────────────────────────────────────
    public int $squadSize = 11;          // read from tournament in mount()
    public string $formation = '4-3-3';

    // Formation sets per squad size. A const (not a public prop) so this static
    // config is NOT serialized into every Livewire request/response payload.
    private const FORMATIONS_BY_SIZE = [
        // 11-a-side: outfield DEF + MID + FWD must total 10 (GK is always the 11th).
        // Ordered most-defensive → most-attacking.
        11 => [
            '6-3-1' => ['DEF' => 6, 'MID' => 3, 'FWD' => 1],
            '5-4-1' => ['DEF' => 5, 'MID' => 4, 'FWD' => 1],
            '5-3-2' => ['DEF' => 5, 'MID' => 3, 'FWD' => 2],
            '5-2-3' => ['DEF' => 5, 'MID' => 2, 'FWD' => 3],
            '4-6-0' => ['DEF' => 4, 'MID' => 6, 'FWD' => 0],
            '4-5-1' => ['DEF' => 4, 'MID' => 5, 'FWD' => 1],
            '4-4-2' => ['DEF' => 4, 'MID' => 4, 'FWD' => 2],
            '4-3-3' => ['DEF' => 4, 'MID' => 3, 'FWD' => 3],
            '4-2-4' => ['DEF' => 4, 'MID' => 2, 'FWD' => 4],
            '3-6-1' => ['DEF' => 3, 'MID' => 6, 'FWD' => 1],
            '3-5-2' => ['DEF' => 3, 'MID' => 5, 'FWD' => 2],
            '3-4-3' => ['DEF' => 3, 'MID' => 4, 'FWD' => 3],
            '3-3-4' => ['DEF' => 3, 'MID' => 3, 'FWD' => 4],
        ],
        // 5-a-side: outfield must total 4 (GK is the 5th). At least one defender.
        5 => [
            '3-1-0' => ['DEF' => 3, 'MID' => 1, 'FWD' => 0],
            '3-0-1' => ['DEF' => 3, 'MID' => 0, 'FWD' => 1],
            '2-2-0' => ['DEF' => 2, 'MID' => 2, 'FWD' => 0],
            '2-1-1' => ['DEF' => 2, 'MID' => 1, 'FWD' => 1],
            '2-0-2' => ['DEF' => 2, 'MID' => 0, 'FWD' => 2],
            '1-3-0' => ['DEF' => 1, 'MID' => 3, 'FWD' => 0],
            '1-2-1' => ['DEF' => 1, 'MID' => 2, 'FWD' => 1],
            '1-1-2' => ['DEF' => 1, 'MID' => 1, 'FWD' => 2],
            '1-0-3' => ['DEF' => 1, 'MID' => 0, 'FWD' => 3],
        ],
    ];

    // ─── Squad state ─────────────────────────────────────────────────
    public int $maxPerTeam = 7;
    public array $selectedIds = [];
    public ?int $captainId = null;
    public ?int $viceCaptainId = null;
    public string $teamName = '';
    public bool $hasExistingSquad = false;

    // ─── UI ──────────────────────────────────────────────────────────
    public string $positionFilter = 'all';
    public string $search = '';
    public string $toast = '';
    public string $toastType = 'success';

    public function mount(): void
    {
        $user = Auth::user();
        $this->userTokens = $user->tokens;
        $this->squadCost = TokenCost::costFor(TokenCost::SQUAD_BUILDER);
        // $this->budget = (int) AppSetting::get(AppSetting::FANTASY_BUDGET, 1500);

        // Load ALL active tournaments for the selector
        $this->tournamentList = Tournament::where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'squad_size'])
            ->toArray();

        // If exactly one active tournament, auto-select it (no need to choose)
        if (count($this->tournamentList) === 1) {
            $this->selectTournament($this->tournamentList[0]['id']);
        }
        $paramFid = (int) request()->query('fixture_id', 0);
        if ($paramFid) {
            $this->selectFixture($paramFid);
        }
    }

    private function loadFixtureList(): void
    {
        $user = Auth::user();
        $submittedFixtureIds = FantasyTeam::where('user_id', $user->id)
            ->whereNotNull('fixture_id')
            ->pluck('fixture_id')
            ->toArray();

        $this->fixtureList = Fixture::with(['homeTeam', 'awayTeam'])
            ->where('tournament_id', $this->activeTournament['id'])
            ->where('status', FixtureStatus::Scheduled)
            ->where('matchday', $this->activeTournament['active_matchday'])
            ->orderBy('matchday')
            ->orderBy('date')
            ->get()
            ->map(fn($f) => [
                'id' => $f->id,
                'matchday' => $f->matchday,
                'date' => $f->date?->format('d M · H:i'),
                'home_team_id' => $f->home_team_id,
                'away_team_id' => $f->away_team_id,
                'home_team_name' => $f->homeTeam?->name ?? 'TBD',
                'away_team_name' => $f->awayTeam?->name ?? 'TBD',
                'home_team_colour' => $f->homeTeam?->colour ?? '#00E676',
                'away_team_colour' => $f->awayTeam?->colour ?? '#3B82F6',
                'has_squad' => in_array($f->id, $submittedFixtureIds),
            ])
            ->toArray();
    }
    public function selectTournament(int $tournamentId): void
    {
        $tournament = Tournament::find($tournamentId);
        if (!$tournament || $tournament->status->value !== 'active')
            return;

        $this->activeTournament = $tournament->toArray();

        // Size-aware setup (moved here from mount)
        $this->squadSize = (int) ($this->activeTournament['squad_size'] ?? 11);
        $this->maxPerTeam = $this->squadSize === 5 ? 3 : 7;

        if ($this->squadSize === 5) {
            $this->budget = (int) AppSetting::get(AppSetting::FANTASY_BUDGET_5, 320);
            $this->formation = '1-2-1';
        } else {
            $this->budget = (int) AppSetting::get(AppSetting::FANTASY_BUDGET, 1500);
            $this->formation = '4-3-3';
        }

        // Reset any in-progress squad/fixture selection
        $this->selectedFixtureId = null;
        $this->selectedFixture = [];
        $this->availablePlayers = [];
        $this->selectedIds = [];
        $this->captainId = null;
        $this->viceCaptainId = null;

        $this->loadFixtureList();
        $this->step = 'select_fixture';
    }
    // ─── Step transitions ────────────────────────────────────────────

    public function selectFixture(int $fixtureId): void
    {
        $fixture = collect($this->fixtureList)->firstWhere('id', $fixtureId);
        if (!$fixture)
            return;

        $this->selectedFixtureId = $fixtureId;
        $this->selectedFixture = $fixture;

        // Players scoped to the two teams in this fixture
        $this->availablePlayers = Player::with('team')
            ->whereIn('team_id', [$fixture['home_team_id'], $fixture['away_team_id']])
            ->orderByDesc('fantasy_price')
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'position' => $p->position->value,
                'fantasy_price' => $p->fantasy_price,
                'team_id' => $p->team_id,
                'team_name' => $p->team->name,
                'team_colour' => $p->team->colour,
            ])->toArray();

        // Reset squad state
        $user = Auth::user();
        $this->selectedIds = [];
        $this->captainId = null;
        $this->viceCaptainId = null;
        $this->teamName = $user->name . "'s XI";
        $this->hasExistingSquad = false;
        $this->positionFilter = 'all';
        $this->search = '';

        // Pre-load if user already has a squad for this fixture
        $existing = FantasyTeam::with('fantasyPicks')
            ->where('user_id', $user->id)
            ->where('fixture_id', $fixtureId)
            ->first();

        if ($existing) {
            $this->hasExistingSquad = true;
            $this->teamName = $existing->team_name;
            $this->formation = $existing->formation ?? '4-3-3';
            foreach ($existing->fantasyPicks as $pick) {
                $this->selectedIds[] = $pick->player_id;
                if ($pick->is_captain)
                    $this->captainId = $pick->player_id;
                if ($pick->is_vice_captain)
                    $this->viceCaptainId = $pick->player_id;
            }
        }

        $this->step = 'build_squad';
    }

    public function backToFixtures(): void
    {
        $this->step = 'select_fixture';
        $this->selectedFixtureId = null;
        $this->selectedFixture = [];
        $this->availablePlayers = [];
        $this->selectedIds = [];
        $this->captainId = null;
        $this->viceCaptainId = null;
        $this->hasExistingSquad = false;
    }

    // ─── Computed helpers ────────────────────────────────────────────

    // Per-request memo. Keyed on selectedIds so it auto-invalidates the moment
    // a player is added/removed; private props aren't serialized by Livewire, so
    // this adds no payload weight and resets cleanly each round-trip.
    private ?array $_selectedCache = null;
    private ?string $_selectedCacheKey = null;

    public function formations(): array
    {
        return self::FORMATIONS_BY_SIZE[$this->squadSize] ?? self::FORMATIONS_BY_SIZE[11];
    }

    public function selectedPlayers(): array
    {
        $key = implode(',', $this->selectedIds);
        if ($this->_selectedCacheKey === $key && $this->_selectedCache !== null) {
            return $this->_selectedCache;
        }

        $this->_selectedCacheKey = $key;
        return $this->_selectedCache = collect($this->availablePlayers)
            ->whereIn('id', $this->selectedIds)
            ->values()
            ->toArray();
    }

    public function spent(): int
    {
        return collect($this->selectedPlayers())->sum('fantasy_price');
    }

    public function budgetRemaining(): int
    {
        return $this->budget - $this->spent();
    }

    public function positionCount(string $pos): int
    {
        return collect($this->selectedPlayers())->where('position', $pos)->count();
    }

    public function teamCount(int $teamId): int
    {
        return collect($this->selectedPlayers())->where('team_id', $teamId)->count();
    }

    // ─── Add / remove ────────────────────────────────────────────────

    public function addPlayer(int $playerId): void
    {
        if (in_array($playerId, $this->selectedIds))
            return;

        $player = collect($this->availablePlayers)->firstWhere('id', $playerId);
        if (!$player)
            return;

        if (count($this->selectedIds) >= $this->squadSize) {
            $this->flash("Squad is full ({$this->squadSize} players).", 'error');
            return;
        }
        if ($player['fantasy_price'] > $this->budgetRemaining()) {
            $this->flash('Not enough budget for this player.', 'error');
            return;
        }

        $shape = $this->formations()[$this->formation];
        $pos = $player['position'];
        $max = $pos === 'GK' ? 1 : ($shape[$pos] ?? 0);
        if ($this->positionCount($pos) >= $max) {
            $this->flash("Formation {$this->formation} only allows {$max} {$pos}.", 'error');
            return;
        }
        if ($this->teamCount($player['team_id']) >= $this->maxPerTeam) {
            $this->flash("Max {$this->maxPerTeam} players from one team.", 'error');
            return;
        }

        $this->selectedIds[] = $playerId;
        $this->flash("Added {$player['name']}.", 'success');
    }

    public function removePlayer(int $playerId): void
    {
        $this->selectedIds = array_values(array_diff($this->selectedIds, [$playerId]));
        if ($this->captainId === $playerId)
            $this->captainId = null;
        if ($this->viceCaptainId === $playerId)
            $this->viceCaptainId = null;
    }

    public function setCaptain(int $playerId): void
    {
        if (!in_array($playerId, $this->selectedIds))
            return;
        if ($this->viceCaptainId === $playerId)
            $this->viceCaptainId = null;
        $this->captainId = $playerId;
    }

    public function setViceCaptain(int $playerId): void
    {
        if (!in_array($playerId, $this->selectedIds))
            return;
        if ($this->captainId === $playerId)
            $this->captainId = null;
        $this->viceCaptainId = $playerId;
    }

    public function changeFormation(string $formation): void
    {
        if (!isset($this->formations()[$formation]))
            return;
        $this->formation = $formation;
        $this->selectedIds = [];
        $this->captainId = null;
        $this->viceCaptainId = null;
        $this->flash("Formation set to {$formation}. Squad reset.", 'success');
    }

    public function suggestSquad(\App\Services\AiService $ai): void
    {
        if (!$this->selectedFixtureId) {
            $this->flash('Pick a fixture first.', 'error');
            return;
        }

        $key = 'ai-suggest:' . Auth::id();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->flash('Easy there — wait a few seconds before asking the AI again.', 'error');
            return;
        }
        RateLimiter::hit($key, 60);

        // Build the player payload from the current pool
        $payload = collect($this->availablePlayers)->map(fn($p) => [
            'id' => $p['id'],
            'name' => $p['name'],
            'position' => $p['position'],
            'fantasy_price' => $p['fantasy_price'],
            'team_name' => $p['team_name'],
        ])->toArray();

        $shape = $this->formations()[$this->formation];

        $result = $ai->suggestSquad($payload, $this->squadSize, $this->budget, $shape, $this->maxPerTeam);

        if (!$result['success']) {
            // Graceful failure — show the specific reason (busy vs network vs other).
            $this->flash($result['message'] . ' You can still pick your squad manually.', 'error');
            return;
        }

        // Clear current selection, then re-add each suggested player THROUGH our own rules.
        $this->selectedIds = [];
        $this->captainId = null;
        $this->viceCaptainId = null;

        $added = 0;
        foreach ($result['player_ids'] as $playerId) {
            // Only add if it's a real player in this pool
            $exists = collect($this->availablePlayers)->firstWhere('id', $playerId);
            if (!$exists)
                continue;

            $before = count($this->selectedIds);
            $this->addPlayer($playerId);          // ← reuses ALL your validation
            if (count($this->selectedIds) > $before)
                $added++;
        }

        // Set captain if the suggested one made it into the squad
        if ($result['captain_id'] && in_array($result['captain_id'], $this->selectedIds)) {
            $this->setCaptain($result['captain_id']);
        }

        if ($added === $this->squadSize) {
            $this->flash("AI suggested a full squad — tweak it however you like!", 'success');
        } else {
            $this->flash("AI filled {$added}/{$this->squadSize}. Adjust the rest yourself.", 'success');
        }
    }

    private function maybeRewardReferrer(): void
    {
        $user = Auth::user();

        // Only if this user was referred
        if (!$user->referred_by)
            return;

        // Find their pending referral that hasn't been rewarded
        $referral = \App\Models\Referral::where('referred_id', $user->id)
            ->where('status', 'pending')
            ->first();

        if (!$referral)
            return;   // already rewarded, or none

        // Guard: never self-reward (shouldn't happen, but safe)
        if ($referral->referrer_id === $user->id)
            return;

        $referrer = \App\Models\User::find($referral->referrer_id);
        if (!$referrer)
            return;

        \Illuminate\Support\Facades\DB::transaction(function () use ($referral, $user, $referrer) {
            // Pay the referrer +20
            $referrer->increment('tokens', 20);

            \App\Models\TokenTransaction::create([
                'user_id' => $referrer->id,
                'type' => \App\Enums\TokenTransactionType::Earned,
                'amount' => 20,
                'description' => "Referral reward: {$user->name} built their first squad",
            ]);

            // Mark the referral completed so it never pays again
            $referral->update([
                'status' => 'completed',
                'rewarded_at' => now(),
            ]);
        });

        // Notify the referrer AFTER the reward is committed. A mail failure must
        // never roll back the token reward, so it lives outside the transaction.
        try {
            \Illuminate\Support\Facades\Mail::to($referrer->email)
                ->queue(new \App\Mail\ReferralSuccessMail($referrer, $user->name, 20));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Referral email failed', [
                'referrer_id' => $referrer->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
    // ─── Save ────────────────────────────────────────────────────────

    public function saveSquad(): void
    {
        if (!$this->selectedFixtureId) {
            $this->flash('No fixture selected.', 'error');
            return;
        }
        if (count($this->selectedIds) !== $this->squadSize) {
            $this->flash("You must pick exactly {$this->squadSize} players.", 'error');
            return;
        }
        if ($this->positionCount('GK') !== 1) {
            $this->flash('You need exactly 1 goalkeeper.', 'error');
            return;
        }
        if (!$this->captainId) {
            $this->flash('Please choose a captain.', 'error');
            return;
        }

        $this->validate(['teamName' => 'required|string|max:50']);

        $user = Auth::user();
        $isFirstSave = !$this->hasExistingSquad;

        if ($isFirstSave && $this->squadCost > 0 && $this->userTokens < $this->squadCost) {
            $this->flash("You need {$this->squadCost} token(s) to enter a squad for this fixture.", 'error');
            return;
        }

        $fixtureId = $this->selectedFixtureId;
        $tournamentId = $this->activeTournament['id'];

        DB::transaction(function () use ($user, $isFirstSave, $fixtureId, $tournamentId) {
            $fantasyTeam = FantasyTeam::updateOrCreate(
                ['user_id' => $user->id, 'fixture_id' => $fixtureId],
                [
                    'tournament_id' => $tournamentId,
                    'team_name' => $this->teamName,
                    'formation' => $this->formation,
                    'budget_remaining' => $this->budgetRemaining(),
                ]
            );

            FantasyPick::where('fantasy_team_id', $fantasyTeam->id)->delete();

            foreach ($this->selectedIds as $playerId) {
                FantasyPick::create([
                    'fantasy_team_id' => $fantasyTeam->id,
                    'fixture_id' => $fixtureId,
                    'player_id' => $playerId,
                    'matchday' => $this->selectedFixture['matchday'] ?? 1,
                    'is_captain' => $playerId === $this->captainId,
                    'is_vice_captain' => $playerId === $this->viceCaptainId,
                    'points_scored' => 0,
                ]);
            }

            if ($isFirstSave && $this->squadCost > 0) {
                $user->decrement('tokens', $this->squadCost);
                $this->userTokens -= $this->squadCost;

                TokenTransaction::create([
                    'user_id' => $user->id,
                    'type' => TokenTransactionType::Spent,
                    'amount' => -$this->squadCost,
                    'description' => "Fantasy squad: {$this->selectedFixture['home_team_name']} vs {$this->selectedFixture['away_team_name']}",
                ]);
            }
        });
        $this->maybeRewardReferrer();

        $this->hasExistingSquad = true;

        // Mark fixture as having a squad in the fixture list
        $fid = $fixtureId;
        $this->fixtureList = collect($this->fixtureList)
            ->map(fn($f) => $f['id'] === $fid ? array_merge($f, ['has_squad' => true]) : $f)
            ->toArray();

        $suffix = ($isFirstSave && $this->squadCost > 0)
            ? " {$this->squadCost} token(s) spent. {$this->userTokens} remaining."
            : ' Squad updated.';

        $this->flash("Squad saved!{$suffix}", 'success');
    }

    // ─── Filtering ───────────────────────────────────────────────────

    public function filteredPlayers(): array
    {
        return collect($this->availablePlayers)
            ->when(
                $this->positionFilter !== 'all',
                fn($c) => $c->where('position', strtoupper($this->positionFilter))
            )
            ->when(
                $this->search,
                fn($c) => $c->filter(fn($p) => str_contains(strtolower($p['name']), strtolower($this->search)))
            )
            ->values()
            ->toArray();
    }

    private function flash(string $msg, string $type = 'success'): void
    {
        $this->toast = $msg;
        $this->toastType = $type;
    }

    public function dismissToast(): void
    {
        $this->toast = '';
    }
} ?>

<div class="max-w-7xl mx-auto px-4 sm:px-8 py-6 sm:py-10 space-y-6">

    {{-- ── Back to previous page ──────────────────────────────────────────────── --}}
    <button type="button" onclick="window.history.back()"
        class="inline-flex items-center gap-1.5 font-mono text-[11px] text-on-surface-variant/60 hover:text-[#00E676] transition-colors cursor-pointer">
        <span class="material-symbols-outlined text-[16px]">arrow_back</span>
        Back
    </button>

    {{-- ── Toast (floating: bottom-centre on mobile, top-right on desktop) ─────── --}}
    @if($toast)
        <div x-data="{ show: true }" x-show="show" x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-3" x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0" x-init="setTimeout(() => { show = false; $wire.dismissToast() }, 4000)"
            class="fixed z-[60] inset-x-4 bottom-4 sm:inset-x-auto sm:bottom-auto sm:top-20 sm:right-4 sm:max-w-sm
                                       p-4 rounded-2xl border shadow-2xl backdrop-blur-md flex items-center justify-between gap-4
                                       {{ $toastType === 'success' ? 'bg-[#00E676]/15 border-[#00E676]/40 text-[#00E676]' : 'bg-red-500/15 border-red-500/40 text-red-400' }}">
            <div class="flex items-center gap-2.5">
                <span
                    class="material-symbols-outlined text-[18px]">{{ $toastType === 'success' ? 'check_circle' : 'warning' }}</span>
                <span class="font-mono text-xs font-semibold">{{ $toast }}</span>
            </div>
            <button @click="show = false" class="opacity-60 hover:opacity-100 cursor-pointer">&times;</button>
        </div>
    @endif

    {{-- ── No active tournament ────────────────────────────────────────────────── --}}
    {{-- No active tournaments at all --}}
    @if(empty($tournamentList))
        <div class="rounded-2xl border border-outline-variant/15 p-12 text-center" style="background: rgba(13,17,15,0.8);">
            <span class="material-symbols-outlined text-4xl text-on-surface-variant/20 block mb-3">sports_soccer</span>
            <h2 class="font-display font-black text-lg text-white mb-2">No Active Tournament</h2>
            <p class="font-mono text-xs text-on-surface-variant/40">There's no tournament open for squad building right now.
            </p>
        </div>

        {{-- STEP 0: pick a tournament --}}
    @elseif($step === 'select_tournament')
        <div class="rounded-2xl border border-outline-variant/15 p-5 sm:p-6"
            style="background: linear-gradient(135deg, rgba(0,230,118,0.06) 0%, rgba(13,17,15,0.9) 60%);">
            <h1 class="font-display font-black text-2xl text-white mb-1">Choose a <span
                    style="color:#00E676;">Competition</span></h1>
            <p class="font-mono text-xs text-on-surface-variant/50 mb-5">Pick which tournament to build a squad for</p>

            <label for="tournament-select"
                class="block text-xs font-mono text-on-surface-variant uppercase tracking-wider mb-2">Tournament</label>
            <select id="tournament-select" wire:change="selectTournament($event.target.value)"
                class="w-full sm:max-w-md px-4 py-3 rounded-xl text-sm text-white border border-outline-variant/20 bg-white/5 focus:outline-none focus:border-[#00E676]/50 transition-all cursor-pointer">
                <option value="" class="bg-surface text-on-surface-variant/60">Select a tournament…</option>
                @foreach($tournamentList as $tourney)
                    <option value="{{ $tourney['id'] }}" class="bg-surface text-on-surface">
                        {{ $tourney['name'] }} · {{ $tourney['squad_size'] }}-a-side
                    </option>
                @endforeach
            </select>
        </div>

    @elseif($step === 'select_fixture')

        {{-- Header --}}
        <div class="rounded-2xl border border-outline-variant/15 p-5 sm:p-6"
            style="background: linear-gradient(135deg, rgba(0,230,118,0.06) 0%, rgba(13,17,15,0.9) 60%);">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <p class="font-mono text-[10px] uppercase tracking-widest text-on-surface-variant/50 mb-1">
                        {{ $activeTournament['name'] }}
                    </p>
                    <h1 class="font-display font-black text-2xl text-white">Fantasy <span
                            style="color:#00E676;">Squad</span></h1>
                    <p class="font-mono text-xs text-on-surface-variant/50 mt-1">Pick a fixture to build your squad for</p>
                </div>
                <div class="flex items-center gap-4 px-4 py-3 rounded-xl border border-outline-variant/20"
                    style="background: rgba(255,255,255,0.03);">
                    <div class="text-center">
                        <span class="block font-black text-xl font-mono" style="color:#00E676;">🪙 {{ $userTokens }}</span>
                        <span class="font-mono text-[9px] uppercase tracking-widest text-on-surface-variant/50">your
                            tokens</span>
                    </div>
                    @if($squadCost > 0)
                        <div class="w-px h-8 bg-outline-variant/20"></div>
                        <div class="text-center">
                            <span class="block font-black text-xl font-mono text-white">{{ $squadCost }}</span>
                            <span class="font-mono text-[9px] uppercase tracking-widest text-on-surface-variant/50">cost /
                                fixture</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Fixture list --}}
        @forelse($fixtureList as $fix)
            <div wire:key="fix-select-{{ $fix['id'] }}"
                class="rounded-2xl border transition-all duration-200
                                                                                                                                                                         {{ $fix['has_squad'] ? 'border-[#00E676]/30' : 'border-outline-variant/15 hover:border-[#00E676]/30' }}"
                style="background: rgba(13,17,15,0.8);">
                <div class="p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-center gap-4">
                        {{-- Matchday badge --}}
                        <div class="flex-shrink-0 w-12 h-12 rounded-xl border border-outline-variant/20 flex flex-col items-center justify-center"
                            style="background: rgba(0,230,118,0.06);">
                            <span class="font-mono text-[8px] text-on-surface-variant/50 uppercase tracking-widest">MD</span>
                            <span class="font-mono font-black text-lg" style="color:#00E676;">{{ $fix['matchday'] }}</span>
                        </div>
                        {{-- Teams --}}
                        <div>
                            <div class="flex items-center gap-3 font-display font-black text-base text-white">
                                <span style="color: {{ $fix['home_team_colour'] }}">{{ $fix['home_team_name'] }}</span>
                                <span class="text-on-surface-variant/30 font-sans font-normal text-xs">vs</span>
                                <span style="color: {{ $fix['away_team_colour'] }}">{{ $fix['away_team_name'] }}</span>
                            </div>
                            @if($fix['date'])
                                <p class="font-mono text-[10px] text-on-surface-variant/40 mt-0.5">{{ $fix['date'] }}</p>
                            @endif
                        </div>
                    </div>
                    <div class="flex items-center gap-3 flex-shrink-0">
                        @if($fix['has_squad'])
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full font-mono text-[10px] font-bold"
                                style="background:rgba(0,230,118,0.1); color:#00E676; border:1px solid rgba(0,230,118,0.3);">
                                <span class="material-symbols-outlined text-[12px]">check_circle</span> Squad submitted
                            </span>
                            <button wire:click="selectFixture({{ $fix['id'] }})"
                                class="px-4 py-2 rounded-xl font-mono text-xs font-bold border border-outline-variant/30 text-on-surface-variant hover:border-[#00E676]/40 hover:text-white transition-all cursor-pointer">
                                Edit Squad
                            </button>
                        @else
                            <button wire:click="selectFixture({{ $fix['id'] }})"
                                class="px-5 py-2.5 rounded-xl font-mono text-xs font-bold uppercase tracking-wider text-black transition-all cursor-pointer hover:scale-[1.02] active:scale-[0.98]"
                                style="background: linear-gradient(135deg, #00E676 0%, #00b359 100%);">
                                Pick Squad
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="rounded-2xl border border-outline-variant/15 p-12 text-center" style="background: rgba(13,17,15,0.8);">
                <span class="material-symbols-outlined text-4xl text-on-surface-variant/20 block mb-3">calendar_month</span>
                <h2 class="font-display font-black text-lg text-white mb-2">No upcoming fixtures</h2>
                <p class="font-mono text-xs text-on-surface-variant/40">Fixtures will appear here once the admin schedules
                    matches.</p>
            </div>
        @endforelse

        {{-- ══════════════════════════════════════════════════════════════════════════ --}}
        {{-- ── STEP 2: BUILD SQUAD ─────────────────────────────────────────────────── --}}
        {{-- ══════════════════════════════════════════════════════════════════════════ --}}
    @else

        {{-- Header bar --}}
        <div class="rounded-2xl border border-outline-variant/15 p-5 sm:p-6"
            style="background: linear-gradient(135deg, rgba(0,230,118,0.06) 0%, rgba(13,17,15,0.9) 60%);">
            <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-5">
                <div>
                    {{-- Back + fixture label --}}
                    <button wire:click="backToFixtures"
                        class="inline-flex items-center gap-1.5 font-mono text-[10px] text-on-surface-variant/50 hover:text-[#00E676] mb-2 transition-colors cursor-pointer">
                        <span class="material-symbols-outlined text-[14px]">arrow_back</span>
                        All fixtures
                    </button>
                    <p class="font-mono text-[10px] uppercase tracking-widest mb-1">
                        <span style="color:#00E676;">Matchday {{ $selectedFixture['matchday'] ?? '—' }}</span>
                        @if($selectedFixture['date'] ?? null) <span class="text-on-surface-variant/50">·
                        {{ $selectedFixture['date'] }}</span> @endif
                    </p>
                    <h1 class="font-display font-black text-xl text-white">
                        <span
                            style="color: {{ $selectedFixture['home_team_colour'] ?? '#00E676' }}">{{ $selectedFixture['home_team_name'] ?? 'Home' }}</span>
                        <span class="text-on-surface-variant/30 font-sans font-normal text-sm mx-2">vs</span>
                        <span
                            style="color: {{ $selectedFixture['away_team_colour'] ?? '#3B82F6' }}">{{ $selectedFixture['away_team_name'] ?? 'Away' }}</span>
                    </h1>
                    @if($hasExistingSquad)
                        <p class="font-mono text-[10px] mt-1" style="color:#00E676;">
                            <span class="material-symbols-outlined text-[12px] align-middle">check_circle</span>
                            Squad already submitted — editing is free
                        </p>
                    @else
                        <p class="font-mono text-[10px] text-on-surface-variant/40 mt-1">
                            First submission costs {{ $squadCost }} token(s)
                        </p>
                    @endif
                </div>
                <div class="flex flex-wrap items-center gap-x-5 gap-y-3 px-5 py-3 rounded-xl border border-outline-variant/20"
                    style="background: rgba(255,255,255,0.03);">
                    <div class="text-center">
                        <span class="block font-black text-2xl font-mono"
                            style="color:#00E676;">{{ $this->budgetRemaining() }}</span>
                        <span class="font-mono text-[9px] uppercase tracking-widest text-on-surface-variant/50">Budget
                            left</span>
                    </div>
                    <div class="hidden sm:block w-px h-10 bg-outline-variant/20"></div>
                    <div class="text-center">
                        <span class="block font-mono text-[10px] text-on-surface-variant/40">of</span>
                        <span class="block font-black text-2xl font-mono text-white">{{ $budget }}</span>
                        <span class="font-mono text-[9px] uppercase tracking-widest text-on-surface-variant/50">Total
                            budget</span>
                    </div>
                    <div class="hidden sm:block w-px h-10 bg-outline-variant/20"></div>
                    <div class="text-center">
                        <span class="block font-black text-2xl font-mono text-white">{{ count($selectedIds) }}<span
                                class="text-on-surface-variant/40 text-lg">/{{ $squadSize }}</span></span>
                        <span
                            class="font-mono text-[9px] uppercase tracking-widest text-on-surface-variant/50">Players</span>
                    </div>
                </div>
            </div>

            <button wire:click="suggestSquad" wire:loading.attr="disabled" wire:target="suggestSquad"
                class="px-4 py-2 rounded-lg text-xs font-mono font-bold uppercase tracking-wider border border-[#00E676]/40 text-[#00E676] hover:bg-[#00E676]/10 transition-all cursor-pointer disabled:opacity-50">
                <span wire:loading.remove wire:target="suggestSquad">✨ Suggest a Squad</span>
                <span wire:loading wire:target="suggestSquad">Thinking…</span>
            </button>
            {{-- Formation pills --}}
            <div class="flex gap-2 mt-5 flex-wrap">
                @foreach(array_keys($this->formations()) as $f)
                    <button wire:click="changeFormation('{{ $f }}')"
                        class="px-3 py-1.5 rounded-lg text-xs font-mono font-bold border transition-all cursor-pointer
                                                                                                                                                                                   {{ $formation === $f ? 'text-black border-transparent' : 'text-on-surface-variant border-outline-variant/20 hover:border-[#00E676]/40' }}"
                        style="{{ $formation === $f ? 'background: linear-gradient(135deg, #00E676 0%, #00b359 100%);' : '' }}">
                        {{ $f }}
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Builder grid --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

            {{-- Pitch --}}
            <div class="lg:col-span-7 rounded-2xl border border-outline-variant/15 p-6"
                style="background: rgba(13,17,15,0.8);">
                <h3 class="font-display font-black text-sm uppercase tracking-wider text-white mb-5">Formation ·
                    {{ $formation }}
                </h3>

                <div id="pitch-surface"
                    class="rounded-2xl border border-[#00E676]/10 p-3 sm:p-6 relative flex flex-col h-[420px] sm:h-[480px] lg:h-[520px] overflow-hidden"
                    style="background: repeating-linear-gradient(0deg, rgba(0,230,118,0.02) 0px, rgba(0,230,118,0.02) 40px, transparent 40px, transparent 80px);"
                    x-data @squad-player-dropped.window="$wire.addPlayer($event.detail.id)">
                    {{-- Overlay while add/remove travels to the server.
                    .delay keeps it hidden for fast responses so quick taps
                    feel instant — it only appears if the round-trip stalls. --}}
                    <div wire:loading.delay wire:target="addPlayer,removePlayer"
                        class="absolute inset-0 z-30 rounded-2xl flex items-center justify-center"
                        style="background: rgba(8,12,10,0.55);">
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full animate-pulse" style="background:#00E676;"></span>
                            <span class="font-mono text-xs font-bold uppercase tracking-widest animate-pulse"
                                style="color:#00E676;">Updating…</span>
                        </div>
                    </div>
                    @php
                        $shape = $this->formations()[$formation];
                        $selected = collect($this->selectedPlayers());
                        $byPos = [
                            'GK' => $selected->where('position', 'GK')->values(),
                            'DEF' => $selected->where('position', 'DEF')->values(),
                            'MID' => $selected->where('position', 'MID')->values(),
                            'FWD' => $selected->where('position', 'FWD')->values(),
                        ];
                        $rows = ['GK' => 1, 'DEF' => $shape['DEF'], 'MID' => $shape['MID'], 'FWD' => $shape['FWD']];
                    @endphp

                    @foreach($rows as $pos => $slotCount)
                        <div class="flex-1 flex justify-center items-center gap-1 sm:gap-2 z-10 min-h-0">
                            @for($i = 0; $i < $slotCount; $i++)
                                @php $player = $byPos[$pos][$i] ?? null; @endphp
                                @if($player)
                                    <button wire:click="removePlayer({{ $player['id'] }})"
                                        class="text-center group cursor-pointer flex-1 min-w-0 max-w-[90px]">
                                        <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-full flex items-center justify-center font-black text-xs mx-auto border-2 shadow-lg md:group-hover:scale-105 transition-transform"
                                            style="background:{{ $player['team_colour'] }}22; border-color:{{ $player['team_colour'] }}; color:{{ $player['team_colour'] }};">
                                            {{ $pos }}
                                        </div>
                                        <span
                                            class="block text-[10px] sm:text-xs font-bold text-white mt-1 truncate max-w-full px-0.5">{{ $player['name'] }}</span>
                                        <span class="font-mono text-[9px]" style="color:#00E676;">{{ $player['fantasy_price'] }}</span>
                                    </button>
                                @else
                                    <div class="text-center flex-1 min-w-0 max-w-[90px]">
                                        <div
                                            class="w-12 h-12 sm:w-14 sm:h-14 rounded-full flex items-center justify-center font-bold text-on-surface-variant/30 text-lg mx-auto border-2 border-dashed border-outline-variant/30">
                                            +</div>
                                        <span class="block text-[10px] text-on-surface-variant/40 mt-1">{{ $pos }}</span>
                                    </div>
                                @endif
                            @endfor
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Player list --}}
            <div class="lg:col-span-5 rounded-2xl border border-outline-variant/15 p-6 flex flex-col h-[500px] lg:h-[600px]"
                style="background: rgba(13,17,15,0.8);">
                <h3 class="font-display font-black text-sm uppercase tracking-wider text-white mb-1">Available Players</h3>
                <p class="font-mono text-[10px] text-on-surface-variant/40 mb-4">
                    {{ $selectedFixture['home_team_name'] ?? '' }} &amp; {{ $selectedFixture['away_team_name'] ?? '' }}
                </p>

                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search players…"
                    class="w-full px-4 py-2.5 rounded-xl text-sm text-white placeholder-on-surface-variant/30 border border-outline-variant/20 bg-white/5 focus:outline-none focus:border-[#00E676]/50 transition-all mb-3" />

                <div class="flex gap-1.5 mb-4">
                    @foreach(['all' => 'All', 'gk' => 'GK', 'def' => 'DEF', 'mid' => 'MID', 'fwd' => 'FWD'] as $val => $label)
                        <button wire:click="$set('positionFilter', '{{ $val }}')"
                            class="px-2.5 py-1 rounded-lg text-[10px] font-mono font-bold border transition-all
                                                                                                                                                                                       {{ $positionFilter === $val ? 'text-black border-transparent' : 'text-on-surface-variant/60 border-outline-variant/20' }}"
                            style="{{ $positionFilter === $val ? 'background:#00E676;' : '' }}">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>

                <div class="flex-1 overflow-y-auto pr-1 space-y-1.5" id="player-list-sortable" x-data
                    style="touch-action: pan-y; overscroll-behavior: contain;">
                    @php
                        $filtered = collect($this->filteredPlayers());
                        $homeTeamId = $selectedFixture['home_team_id'] ?? null;
                        $awayTeamId = $selectedFixture['away_team_id'] ?? null;
                        $homeColour = $selectedFixture['home_team_colour'] ?? '#00E676';
                        $awayColour = $selectedFixture['away_team_colour'] ?? '#3B82F6';
                        $homeName = $selectedFixture['home_team_name'] ?? 'Home';
                        $awayName = $selectedFixture['away_team_name'] ?? 'Away';
                        $homePlayers = $homeTeamId ? $filtered->where('team_id', $homeTeamId)->values() : collect();
                        $awayPlayers = $awayTeamId ? $filtered->where('team_id', $awayTeamId)->values() : collect();
                        $homePickedCount = collect($availablePlayers)->whereIn('id', $selectedIds)->where('team_id', $homeTeamId)->count();
                        $awayPickedCount = collect($availablePlayers)->whereIn('id', $selectedIds)->where('team_id', $awayTeamId)->count();
                    @endphp

                    @if($filtered->isEmpty())
                        <p class="text-center font-mono text-xs text-on-surface-variant/40 py-8">No players found.</p>
                    @else
                        {{-- ── Home Team ─────────────────────────────────────────── --}}
                        @if($homePlayers->isNotEmpty())
                            {{-- Section header --}}
                            <div wire:key="section-home-{{ $homeTeamId }}"
                                class="flex items-center gap-2 mb-2 mt-1 sticky top-0 py-1.5 z-10"
                                style="background: rgba(13,17,15,0.97);">
                                <div class="h-px flex-1 rounded" style="background: {{ $homeColour }}35;"></div>
                                <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-full font-mono text-[9px] font-black uppercase tracking-wider flex-shrink-0"
                                    style="background: {{ $homeColour }}15; color: {{ $homeColour }}; border: 1px solid {{ $homeColour }}35;">
                                    <span class="w-1.5 h-1.5 rounded-full flex-shrink-0"
                                        style="background: {{ $homeColour }};"></span>
                                    {{ $homeName }}
                                    <span class="opacity-60 ml-0.5">{{ $homePickedCount }}/{{ $maxPerTeam }}</span>
                                </div>
                                <div class="h-px flex-1 rounded" style="background: {{ $homeColour }}35;"></div>
                            </div>
                            {{-- Home players --}}
                            @foreach($homePlayers as $player)
                                @php $isPicked = in_array($player['id'], $selectedIds); @endphp
                                <div wire:key="player-{{ $player['id'] }}" data-player-id="{{ $player['id'] }}"
                                    data-position="{{ $player['position'] }}"
                                    class="flex items-center justify-between p-3 rounded-xl border transition-all select-none md:cursor-grab md:active:cursor-grabbing
                                                                                                                                                                                                                                                                                                                                            {{ $isPicked ? 'border-[#00E676]/40 bg-[#00E676]/5' : 'border-outline-variant/15 bg-white/[0.02]' }}">
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <span class="font-mono text-[9px] font-black uppercase px-1.5 py-0.5 rounded flex-shrink-0"
                                            style="background:{{ $player['team_colour'] }}22; color:{{ $player['team_colour'] }};">{{ $player['position'] }}</span>
                                        <span class="block font-bold text-sm text-white truncate">{{ $player['name'] }}</span>
                                    </div>
                                    <div class="flex items-center gap-3 flex-shrink-0">
                                        <span class="font-mono text-xs font-bold"
                                            style="color:#00E676;">{{ $player['fantasy_price'] }}</span>
                                        @if($isPicked)
                                            <button wire:click="removePlayer({{ $player['id'] }})"
                                                @click="$el.closest('[data-player-id]').style.opacity = '0.5'"
                                                class="w-8 h-8 rounded-lg bg-red-500/15 text-red-400 border border-red-500/20 flex items-center justify-center hover:bg-red-500 hover:text-white transition-all cursor-pointer font-bold">&times;</button>
                                        @else
                                            <button wire:click="addPlayer({{ $player['id'] }})"
                                                @click="$el.closest('[data-player-id]').style.opacity = '0.5'"
                                                class="w-8 h-8 rounded-lg bg-white/5 text-on-surface-variant/60 border border-outline-variant/20 flex items-center justify-center hover:bg-[#00E676] hover:text-black transition-all cursor-pointer font-bold">+</button>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        @endif

                        {{-- ── Away Team ─────────────────────────────────────────── --}}
                        @if($awayPlayers->isNotEmpty())
                            {{-- Section header --}}
                            <div wire:key="section-away-{{ $awayTeamId }}"
                                class="flex items-center gap-2 mb-2 !mt-3 sticky top-0 py-1.5 z-10"
                                style="background: rgba(13,17,15,0.97);">
                                <div class="h-px flex-1 rounded" style="background: {{ $awayColour }}35;"></div>
                                <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-full font-mono text-[9px] font-black uppercase tracking-wider flex-shrink-0"
                                    style="background: {{ $awayColour }}15; color: {{ $awayColour }}; border: 1px solid {{ $awayColour }}35;">
                                    <span class="w-1.5 h-1.5 rounded-full flex-shrink-0"
                                        style="background: {{ $awayColour }};"></span>
                                    {{ $awayName }}
                                    <span class="opacity-60 ml-0.5">{{ $awayPickedCount }}/{{ $maxPerTeam }}</span>
                                </div>
                                <div class="h-px flex-1 rounded" style="background: {{ $awayColour }}35;"></div>
                            </div>
                            {{-- Away players --}}
                            @foreach($awayPlayers as $player)
                                @php $isPicked = in_array($player['id'], $selectedIds); @endphp
                                <div wire:key="player-{{ $player['id'] }}" data-player-id="{{ $player['id'] }}"
                                    data-position="{{ $player['position'] }}"
                                    class="flex items-center justify-between p-3 rounded-xl border transition-all select-none md:cursor-grab md:active:cursor-grabbing
                                                                                                                                                                                                                                                                                                                                            {{ $isPicked ? 'border-[#00E676]/40 bg-[#00E676]/5' : 'border-outline-variant/15 bg-white/[0.02]' }}">
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <span class="font-mono text-[9px] font-black uppercase px-1.5 py-0.5 rounded flex-shrink-0"
                                            style="background:{{ $player['team_colour'] }}22; color:{{ $player['team_colour'] }};">{{ $player['position'] }}</span>
                                        <span class="block font-bold text-sm text-white truncate">{{ $player['name'] }}</span>
                                    </div>
                                    <div class="flex items-center gap-3 flex-shrink-0">
                                        <span class="font-mono text-xs font-bold"
                                            style="color:#00E676;">{{ $player['fantasy_price'] }}</span>
                                        @if($isPicked)
                                            <button wire:click="removePlayer({{ $player['id'] }})"
                                                @click="$el.closest('[data-player-id]').style.opacity = '0.5'"
                                                class="w-8 h-8 rounded-lg bg-red-500/15 text-red-400 border border-red-500/20 flex items-center justify-center hover:bg-red-500 hover:text-white transition-all cursor-pointer font-bold">&times;</button>
                                        @else
                                            <button wire:click="addPlayer({{ $player['id'] }})"
                                                @click="$el.closest('[data-player-id]').style.opacity = '0.5'"
                                                class="w-8 h-8 rounded-lg bg-white/5 text-on-surface-variant/60 border border-outline-variant/20 flex items-center justify-center hover:bg-[#00E676] hover:text-black transition-all cursor-pointer font-bold">+</button>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        @endif
                    @endif
                </div>
            </div>
        </div>

        {{-- Save footer --}}
        <div class="rounded-2xl border border-outline-variant/15 p-6 space-y-5" style="background: rgba(13,17,15,0.8);">
            {{-- Team name --}}
            <div>
                <label class="block text-xs font-mono text-on-surface-variant uppercase tracking-wider mb-2">Team
                    Name</label>
                <input wire:model="teamName" type="text" placeholder="Your squad name"
                    class="w-full sm:max-w-xs px-4 py-2.5 rounded-xl text-sm text-white placeholder-on-surface-variant/30 border border-outline-variant/20 bg-white/5 focus:outline-none focus:border-[#00E676]/50 transition-all" />
                @error('teamName') <p class="text-red-400 text-xs font-mono mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Captain / Vice-captain --}}
            @if(count($selectedIds) > 0)
                <div>
                    <label class="block text-xs font-mono text-on-surface-variant uppercase tracking-wider mb-2">
                        Captain <span style="color:#00E676;">(C)</span> &amp; Vice-Captain <span
                            class="text-on-surface-variant/60">(V)</span>
                    </label>
                    <div class="space-y-2 max-h-48 overflow-y-auto pr-1">
                        @foreach($this->selectedPlayers() as $player)
                            <div
                                class="flex items-center justify-between p-2.5 rounded-xl border border-outline-variant/15 bg-white/[0.02]">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <span class="font-mono text-[9px] font-black uppercase px-1.5 py-0.5 rounded flex-shrink-0"
                                        style="background:{{ $player['team_colour'] }}22; color:{{ $player['team_colour'] }};">{{ $player['position'] }}</span>
                                    <span class="font-bold text-sm text-white truncate">{{ $player['name'] }}</span>
                                </div>
                                <div class="flex items-center gap-2 flex-shrink-0">
                                    <button wire:click="setCaptain({{ $player['id'] }})"
                                        class="w-7 h-7 rounded-lg font-black text-xs border transition-all cursor-pointer
                                                                                                                                                                                                                                                                           {{ $captainId === $player['id'] ? 'text-black border-transparent' : 'text-on-surface-variant/50 border-outline-variant/20 hover:border-[#00E676]/40' }}"
                                        style="{{ $captainId === $player['id'] ? 'background:#00E676;' : '' }}"
                                        title="Captain">C</button>
                                    <button wire:click="setViceCaptain({{ $player['id'] }})"
                                        class="w-7 h-7 rounded-lg font-black text-xs border transition-all cursor-pointer
                                                                                                                                                                                                                                                                           {{ $viceCaptainId === $player['id'] ? 'bg-white/15 text-white border-white/30' : 'text-on-surface-variant/50 border-outline-variant/20 hover:border-white/30' }}"
                                        title="Vice-captain">V</button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Save button --}}
            <div class="flex items-center justify-between gap-4 pt-2 border-t border-outline-variant/10">
                <p class="font-mono text-[10px] text-on-surface-variant/40">
                    {{ count($selectedIds) }}/{{ $squadSize }} picked
                    @if(count($selectedIds) === $squadSize) · {{ $captainId ? 'Captain set ✓' : 'Choose a captain' }} @endif
                </p>
                <button wire:click="saveSquad" wire:loading.attr="disabled"
                    class="px-6 py-2.5 rounded-xl text-xs font-mono font-bold uppercase tracking-wider text-black transition-all cursor-pointer disabled:opacity-50"
                    style="background: linear-gradient(135deg, #00E676 0%, #00b359 100%);">
                    <span wire:loading.remove wire:target="saveSquad">
                        @if(!$hasExistingSquad && $squadCost > 0)
                            Save Squad · {{ $squadCost }} token(s)
                        @else
                            Save Squad
                        @endif
                    </span>
                    <span wire:loading wire:target="saveSquad">Saving…</span>
                </button>
            </div>
        </div>

    @endif
</div>