<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Fixture;
use App\Models\PlayerEvent;
use App\Models\Player;
use App\Enums\FixtureStatus;
use App\Enums\PlayerEventType;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use App\Models\FixturePlayerStat;

new #[Layout('layouts.admin')] class extends Component {

    public array $fixtures = [];
    public array $lineup = [];
    public string $search = '';

    public string $aiSummary = '';
    public array $aiWarnings = [];
    public string $aiMessage = '';
    public array $topPerformers = [];

    public ?int $selectedFixtureId = null;
    public ?array $selectedFixture = null;

    public string $home_score = '';
    public string $away_score = '';
    public string $fixture_status = 'completed';

    public array $homePlayers = [];
    public array $awayPlayers = [];
    public array $events = [];

    public string $event_player_id = '';
    public string $event_type = 'goal';
    public string $event_minute = '';
    public bool $event_is_substitute = false;

    public bool $showPanel = false;
    public string $resultsTab = 'pending';

    public function mount(): void
    {
        $this->loadFixtures();
    }

    public function updatedSearch(): void
    {
        $this->loadFixtures();
    }

    public function updatedResultsTab(): void
    {
        $this->showPanel = false;
        $this->selectedFixtureId = null;
        $this->loadFixtures();
    }

    public function loadFixtures(): void
    {
        $this->fixtures = Fixture::with(['homeTeam', 'awayTeam', 'tournament'])
            ->when(
                $this->resultsTab === 'pending',
                fn($q) => $q->whereIn('status', ['scheduled', 'live', 'postponed'])
            )
            ->when(
                $this->resultsTab === 'completed',
                fn($q) => $q->where('status', 'completed')
            )
            ->when(
                $this->search,
                fn($q) =>
                $q->where(
                    fn($sub) =>
                    $sub->whereHas('homeTeam', fn($q) => $q->where('name', 'like', "%{$this->search}%"))
                        ->orWhereHas('awayTeam', fn($q) => $q->where('name', 'like', "%{$this->search}%"))
                )
            )
            ->orderBy('matchday')
            ->orderBy('date')
            ->get()
            ->map(fn($f) => [
                'id' => $f->id,
                'matchday' => $f->matchday ?? 1,
                'date' => $f->date,
                'home_score' => $f->home_score,
                'away_score' => $f->away_score,
                'status' => $f->status instanceof FixtureStatus ? $f->status->value : ($f->status ?? 'scheduled'),
                'home_team' => ['id' => $f->homeTeam?->id ?? 0, 'name' => $f->homeTeam?->name ?? 'TBD'],
                'away_team' => ['id' => $f->awayTeam?->id ?? 0, 'name' => $f->awayTeam?->name ?? 'TBD'],
                'tournament' => ['id' => $f->tournament?->id ?? 0, 'name' => $f->tournament?->name ?? '—'],
            ])
            ->values()
            ->toArray();
    }

    public function openPanel(int $fixtureId): void
    {
        $fixture = Fixture::with([
            'homeTeam.players',
            'awayTeam.players',
            'playerEvents.player',
            'tournament',
        ])->findOrFail($fixtureId);

        $statusValue = $fixture->status instanceof FixtureStatus
            ? $fixture->status->value
            : ($fixture->status ?? 'scheduled');

        $this->selectedFixtureId = $fixtureId;
        $this->selectedFixture = [
            'id' => $fixture->id,
            'matchday' => $fixture->matchday ?? 1,
            'home_score' => $fixture->home_score,
            'away_score' => $fixture->away_score,
            'status' => $statusValue,
            'home_team' => ['id' => $fixture->homeTeam?->id ?? 0, 'name' => $fixture->homeTeam?->name ?? 'TBD'],
            'away_team' => ['id' => $fixture->awayTeam?->id ?? 0, 'name' => $fixture->awayTeam?->name ?? 'TBD'],
            'tournament' => ['id' => $fixture->tournament?->id ?? 0, 'name' => $fixture->tournament?->name ?? '—'],
        ];

        $this->home_score = (string) ($fixture->home_score ?? '');
        $this->away_score = (string) ($fixture->away_score ?? '');
        $this->fixture_status = $statusValue;

        $this->homePlayers = $fixture->homeTeam
            ? $fixture->homeTeam->players->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'position' => $p->position instanceof \BackedEnum ? $p->position->value : ($p->position ?? 'N/A'),
            ])->toArray()
            : [];

        $this->awayPlayers = $fixture->awayTeam
            ? $fixture->awayTeam->players->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'position' => $p->position instanceof \BackedEnum ? $p->position->value : ($p->position ?? 'N/A'),
            ])->toArray()
            : [];

        $existingStats = FixturePlayerStat::where('fixture_id', $fixtureId)
            ->get()
            ->keyBy('player_id');

        $this->lineup = [];
        $this->topPerformers = FixturePlayerStat::where('fixture_id', $fixtureId)
            ->where('bonus', '>', 0)
            ->orderByDesc('bonus')
            ->pluck('player_id')
            ->toArray();
        foreach (array_merge($this->homePlayers, $this->awayPlayers) as $p) {
            $stat = $existingStats->get($p['id']);
            $this->lineup[$p['id']] = [
                'minutes' => $stat ? $stat->minutes_played : 90,
                'saves' => $stat?->saves ?? 0,
            ];
        }

        $this->events = $fixture->playerEvents->map(fn($e) => [
            'id' => $e->id,
            'player_id' => $e->player_id,
            'player_name' => $e->player?->name ?? 'Unknown',
            'event_type' => $e->event_type instanceof \BackedEnum ? $e->event_type->value : ($e->event_type ?? 'goal'),
            'minute' => $e->minute,
            'is_substitute' => $e->is_substitute,
        ])->toArray();

        $this->resetEventForm();
        $this->showPanel = true;
    }

    public function parseResult(\App\Services\AiService $ai): void
    {
        if (!$this->selectedFixtureId) {
            $this->aiMessage = 'Open a fixture first.';
            return;
        }
        if (trim($this->aiSummary) === '') {
            $this->aiMessage = 'Type a match summary first.';
            return;
        }

        // The roster we already loaded in openPanel() — names + IDs for THIS fixture
        $players = collect(array_merge($this->homePlayers, $this->awayPlayers))
            ->map(fn($p) => [
                'id' => $p['id'],
                'name' => $p['name'],
                'position' => $p['position'],
                'team_name' => '',   // optional; team context helps but isn't required
            ])->toArray();

        // Add team context so the AI knows which side is which
        foreach ($players as $i => $p) {
            $isHome = collect($this->homePlayers)->firstWhere('id', $p['id']) !== null;
            $players[$i]['team_name'] = $isHome
                ? ($this->selectedFixture['home_team']['name'] ?? 'Home')
                : ($this->selectedFixture['away_team']['name'] ?? 'Away');
        }

        $result = $ai->structureResult(
            $this->aiSummary,
            $players,
            $this->selectedFixture['home_team']['name'] ?? 'Home',
            $this->selectedFixture['away_team']['name'] ?? 'Away'
        );

        if (!$result['success']) {
            $this->aiMessage = $result['message'] . ' Or enter the result manually.';
            $this->aiWarnings = [];
            return;
        }

        // Fill the scores (form inputs are strings)
        if ($result['home_score'] !== null)
            $this->home_score = (string) $result['home_score'];
        if ($result['away_score'] !== null)
            $this->away_score = (string) $result['away_score'];
        if (!empty($result['status'])) {
            $this->fixture_status = $result['status'];
        }

        $this->events = [];
        $allPlayers = array_merge($this->homePlayers, $this->awayPlayers);
        foreach ($result['events'] as $e) {
            $player = collect($allPlayers)->firstWhere('id', $e['player_id']);
            if (!$player)
                continue;   // safety: only real players in this fixture

            $this->events[] = [
                'id' => null,
                'player_id' => $e['player_id'],
                'player_name' => $player['name'],
                'event_type' => $e['event_type'],
                'minute' => $e['minute'],
                'is_substitute' => $e['is_substitute'],
            ];
        }
        foreach ($result['lineup'] ?? [] as $l) {
            $pid = $l['player_id'];
            if (isset($this->lineup[$pid])) {
                $this->lineup[$pid]['minutes'] = $l['minutes'];
                $this->lineup[$pid]['saves'] = $l['saves'];
            }
        }

        if (!empty($result['top_performers'])) {
            $this->topPerformers = $result['top_performers'];
        }

        $this->aiWarnings = $result['warnings'];
        $count = count($result['events']);
        $this->aiMessage = "Added {$count} event(s). " .
            (count($result['warnings']) ? "Some items need your attention below — add them manually." : "Review and save.");
    }

    public function addEvent(): void
    {
        $this->validate([
            'event_player_id' => 'required|exists:players,id',
            'event_type' => ['required', Rule::enum(PlayerEventType::class)],
            'event_minute' => 'nullable|integer|min:1|max:120',
        ]);

        $allPlayers = array_merge($this->homePlayers, $this->awayPlayers);
        $player = collect($allPlayers)->firstWhere('id', (int) $this->event_player_id);

        $this->events[] = [
            'id' => null,
            'player_id' => (int) $this->event_player_id,
            'player_name' => $player['name'] ?? 'Unknown',
            'event_type' => $this->event_type,
            'minute' => $this->event_minute ?: null,
            'is_substitute' => $this->event_is_substitute,
        ];

        $this->resetEventForm();
    }

    public function removeEvent(int $index): void
    {
        if (!empty($this->events[$index]['id'])) {
            PlayerEvent::find($this->events[$index]['id'])?->delete();
        }
        array_splice($this->events, $index, 1);
    }

    public function saveResult(): void
    {
        $this->validate([
            'home_score' => 'required|integer|min:0|max:99',
            'away_score' => 'required|integer|min:0|max:99',
            'fixture_status' => ['required', Rule::enum(FixtureStatus::class)],
        ]);

        DB::transaction(function () {
            $fixture = Fixture::findOrFail($this->selectedFixtureId);

            $fixture->update([
                'home_score' => (int) $this->home_score,
                'away_score' => (int) $this->away_score,
                'status' => $this->fixture_status,
            ]);

            PlayerEvent::where('fixture_id', $this->selectedFixtureId)->delete();

            foreach ($this->events as $event) {
                PlayerEvent::create([
                    'fixture_id' => $this->selectedFixtureId,
                    'player_id' => $event['player_id'],
                    'event_type' => $event['event_type'],
                    'minute' => $event['minute'],
                    'is_substitute' => $event['is_substitute'],
                ]);
            }

            $playerIds = collect($this->events)->pluck('player_id')->unique();
            foreach ($playerIds as $playerId) {
                Player::where('id', $playerId)->update([
                    'goals' => PlayerEvent::where('player_id', $playerId)->where('event_type', 'goal')->count(),
                    'assists' => PlayerEvent::where('player_id', $playerId)->where('event_type', 'assist')->count(),
                    'yellow_cards' => PlayerEvent::where('player_id', $playerId)->where('event_type', 'yellow')->count(),
                    'red_cards' => PlayerEvent::where('player_id', $playerId)->where('event_type', 'red')->count(),
                ]);
            }

            // Save lineup stats INSIDE the transaction, BEFORE dispatching
            foreach ($this->lineup as $playerId => $stat) {
                FixturePlayerStat::updateOrCreate(
                    ['fixture_id' => $this->selectedFixtureId, 'player_id' => $playerId],
                    [
                        'minutes_played' => (int) ($stat['minutes'] ?? 0),
                        'saves' => (int) ($stat['saves'] ?? 0),
                    ]
                );
            }
            // Reset all bonuses, then apply the top-3
            FixturePlayerStat::where('fixture_id', $this->selectedFixtureId)->update(['bonus' => 0]);

            $bonusByRank = [0 => 3, 1 => 2, 2 => 1];
            foreach ($this->topPerformers as $rank => $playerId) {
                if (!$playerId)
                    continue;
                $stat = FixturePlayerStat::firstOrNew([
                    'fixture_id' => $this->selectedFixtureId,
                    'player_id' => (int) $playerId,
                ]);
                $stat->bonus = $bonusByRank[$rank] ?? 0;
                if (!$stat->exists || $stat->minutes_played <= 0) {
                    $stat->minutes_played = $stat->minutes_played ?: 90;
                }
                $stat->save();
            }
             
            foreach ($this->topPerformers as $rank => $playerId) {
                if (!$playerId)
                    continue;
                $stat = FixturePlayerStat::firstOrNew([
                    'fixture_id' => $this->selectedFixtureId,
                    'player_id' => (int) $playerId,
                ]);
                $stat->bonus = $bonusByRank[$rank] ?? 0;
                if (!$stat->exists || $stat->minutes_played <= 0) {
                    $stat->minutes_played = $stat->minutes_played ?: 90;  // ensure they count
                }
                $stat->save();
            }
        });

        // Dispatch scoring AFTER the transaction commits, so all data is saved
        \App\Jobs\ScoreFixtureJob::dispatchSync($this->selectedFixtureId);

        $this->showPanel = false;
        $this->selectedFixtureId = null;

        // Move to completed tab after marking a fixture completed
        if ($this->fixture_status === 'completed') {
            $this->resultsTab = 'completed';
        }

        $this->loadFixtures();
    }

    public function closePanel(): void
    {
        $this->showPanel = false;
        $this->selectedFixtureId = null;
        $this->events = [];
    }

    private function resetEventForm(): void
    {
        $this->event_player_id = '';
        $this->event_type = 'goal';
        $this->event_minute = '';
        $this->event_is_substitute = false;
    }

    public function setAllMinutes(int $mins): void
    {
        foreach ($this->lineup as $playerId => $stat) {
            $this->lineup[$playerId]['minutes'] = $mins;
        }
    }

    public function statusStyle(string $status): string
    {
        return match ($status) {
            'completed' => 'background:rgba(0,230,118,0.08);color:#00E676;border-color:rgba(0,230,118,0.2);',
            'live' => 'background:rgba(239,68,68,0.08);color:#f87171;border-color:rgba(239,68,68,0.2);',
            'postponed' => 'background:rgba(253,212,0,0.08);color:#fdd400;border-color:rgba(253,212,0,0.2);',
            default => 'background:rgba(180,180,180,0.08);color:#9ca3af;border-color:rgba(180,180,180,0.2);',
        };
    }

    public function eventStyle(string $type): string
    {
        return match ($type) {
            'goal' => 'color:#00E676;',
            'assist' => 'color:#a78bfa;',
            'yellow' => 'color:#fdd400;',
            'red' => 'color:#f87171;',
            'own_goal' => 'color:#fb923c;',
            'penalty_saved' => 'color:#60a5fa;',
            'penalty_miss' => 'color:#fb923c;',
            'sub_on' => 'color:#34d399;',
            'sub_off' => 'color:#94a3b8;',
            default => 'color:#9ca3af;',
        };
    }

} ?>

<div class="space-y-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="font-display font-black text-2xl text-on-surface uppercase tracking-tight">Results</h2>
            <p class="text-on-surface-variant/60 text-xs mt-1 font-mono">Enter scores and log player events for
                fixtures.</p>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="flex gap-1 p-1 rounded-xl border border-outline-variant/15" style="background:rgba(255,255,255,0.02);">
        <button wire:click="$set('resultsTab', 'pending')"
            class="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg font-mono text-xs font-bold uppercase tracking-wider transition-all duration-150 cursor-pointer"
            style="{{ $resultsTab === 'pending'
    ? 'background:rgba(0,230,118,0.12); color:#00E676; border:1px solid rgba(0,230,118,0.25);'
    : 'color:rgba(255,255,255,0.4); border:1px solid transparent;' }}">
            <span class="material-symbols-outlined text-[14px]">pending</span>
            Pending
        </button>
        <button wire:click="$set('resultsTab', 'completed')"
            class="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg font-mono text-xs font-bold uppercase tracking-wider transition-all duration-150 cursor-pointer"
            style="{{ $resultsTab === 'completed'
    ? 'background:rgba(0,230,118,0.12); color:#00E676; border:1px solid rgba(0,230,118,0.25);'
    : 'color:rgba(255,255,255,0.4); border:1px solid transparent;' }}">
            <span class="material-symbols-outlined text-[14px]">check_circle</span>
            Completed
        </button>
    </div>

    {{-- Search --}}
    <div class="relative">
        <span
            class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant/40 text-[16px]">search</span>
        <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search by team name..."
            class="w-full pl-9 pr-4 py-2.5 rounded-xl border border-outline-variant/20 text-sm text-on-surface placeholder:text-on-surface-variant/30 focus:outline-none focus:border-[#00E676]/40 transition-all font-mono"
            style="background:rgba(255,255,255,0.05);" />
    </div>

    {{-- Fixtures table --}}
    <div class="rounded-2xl overflow-hidden border border-outline-variant/15" style="background: rgba(13,17,15,0.8);">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-outline-variant/15" style="background: rgba(255,255,255,0.02);">
                        <th
                            class="py-3.5 px-5 text-xs font-semibold text-on-surface-variant uppercase tracking-wider font-mono">
                            Fixture</th>
                        <th
                            class="py-3.5 px-5 text-xs font-semibold text-on-surface-variant uppercase tracking-wider font-mono text-center">
                            Score</th>
                        <th
                            class="py-3.5 px-5 text-xs font-semibold text-on-surface-variant uppercase tracking-wider font-mono hidden sm:table-cell">
                            MD</th>
                        <th
                            class="py-3.5 px-5 text-xs font-semibold text-on-surface-variant uppercase tracking-wider font-mono">
                            Status</th>
                        <th
                            class="py-3.5 px-5 text-xs font-semibold text-on-surface-variant uppercase tracking-wider font-mono text-right">
                            Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($fixtures as $fixture)
                                    <tr class="border-b border-outline-variant/10 text-sm transition-colors duration-150
                                                                                                                                                                                                                                                                                                                   {{ $selectedFixtureId === $fixture['id'] ? '' : 'hover:bg-white/[0.02]' }}"
                                        style="{{ $selectedFixtureId === $fixture['id'] ? 'background:rgba(0,230,118,0.04);' : '' }}">
                                        <td class="py-4 px-3 sm:px-5 max-w-[160px] sm:max-w-none">
                                            <div class="flex items-center gap-1.5 min-w-0">
                                                <span
                                                    class="font-bold text-on-surface truncate">{{ $fixture['home_team']['name'] ?? 'TBD' }}</span>
                                                <span class="text-on-surface-variant/40 font-mono text-xs flex-shrink-0">vs</span>
                                                <span
                                                    class="font-bold text-on-surface truncate">{{ $fixture['away_team']['name'] ?? 'TBD' }}</span>
                                            </div>
                                            <span
                                                class="font-mono text-[10px] text-on-surface-variant/40 block truncate">{{ $fixture['tournament']['name'] ?? '—' }}</span>
                                        </td>
                                        <td class="py-4 px-3 sm:px-5 text-center">
                                            @if(isset($fixture['home_score'], $fixture['away_score']) && $fixture['home_score'] !== null && $fixture['away_score'] !== null)
                                                <span class="font-black font-mono text-lg" style="color:#00E676;">
                                                    {{ $fixture['home_score'] }} — {{ $fixture['away_score'] }}
                                                </span>
                                            @else
                                                <span class="font-mono text-on-surface-variant/30 text-xs">— : —</span>
                                            @endif
                                        </td>
                                        <td class="py-4 px-3 sm:px-5 hidden sm:table-cell">
                                            <span
                                                class="font-mono text-[9px] font-bold text-on-surface-variant bg-white/5 px-2 py-0.5 rounded border border-outline-variant/20">
                                                MD{{ $fixture['matchday'] ?? 1 }}
                                            </span>
                                        </td>
                                        <td class="py-4 px-3 sm:px-5">
                                            @php $status = $fixture['status'] ?? 'scheduled'; @endphp
                                            <span
                                                class="px-2.5 py-0.5 rounded-full font-mono text-[9px] font-bold uppercase tracking-widest border {{ $status === 'live' ? 'animate-pulse' : '' }}"
                                                style="{{ $this->statusStyle($status) }}">
                                                {{ $status }}
                                            </span>
                                        </td>
                                        <td class="py-4 px-3 sm:px-5 text-right">
                                            <button wire:click="openPanel({{ $fixture['id'] }})"
                                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-mono font-bold border transition-all duration-150 cursor-pointer"
                                                style="{{ $selectedFixtureId === $fixture['id']
                        ? 'background:#00E676;color:#000;border-color:#00E676;'
                        : 'background:rgba(0,230,118,0.08);color:#00E676;border-color:rgba(0,230,118,0.25);' }}">
                                                <span class="material-symbols-outlined text-[14px]">edit_note</span>
                                                <span class="hidden sm:inline">
                                                    {{ $selectedFixtureId === $fixture['id'] ? 'Editing' : ($resultsTab === 'completed' ? 'Amend Result' : 'Enter Result') }}
                                                </span>
                                            </button>
                                        </td>
                                    </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-16 text-center">
                                <span
                                    class="material-symbols-outlined text-4xl text-on-surface-variant/20 block mb-3">sports_score</span>
                                <p class="text-on-surface-variant/40 text-sm font-mono">
                                    {{ $resultsTab === 'completed' ? 'No completed fixtures yet.' : 'No pending fixtures. All results may already be entered.' }}
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════ --}}
    {{-- RESULT ENTRY PANEL — inline card, no fixed overlay --}}
    {{-- ═══════════════════════════════════════════════════════════════ --}}
    @if($showPanel && $selectedFixture)
        <div class="rounded-2xl border border-outline-variant/20 overflow-hidden" style="background:#0d110f;">

            {{-- Panel header --}}
            <div class="flex items-center justify-between px-4 sm:px-6 py-4 border-b border-outline-variant/15"
                style="background:rgba(0,230,118,0.03);">
                <div class="min-w-0 flex-1 mr-3">
                    <div class="flex items-center gap-2 mb-0.5 min-w-0">
                        <span class="w-1.5 h-1.5 rounded-full flex-shrink-0 animate-pulse"
                            style="background:#00E676;"></span>
                        <h3 class="font-display font-black text-base text-on-surface truncate">
                            {{ $selectedFixture['home_team']['name'] ?? 'TBD' }}
                            <span class="text-on-surface-variant/40 mx-1 font-mono text-sm font-normal">vs</span>
                            {{ $selectedFixture['away_team']['name'] ?? 'TBD' }}
                        </h3>
                    </div>
                    <p class="font-mono text-[10px] text-on-surface-variant/40 truncate">
                        MD{{ $selectedFixture['matchday'] ?? 1 }} · {{ $selectedFixture['tournament']['name'] ?? '—' }}
                    </p>
                </div>
                <button wire:click="closePanel"
                    class="text-on-surface-variant/50 hover:text-white transition-colors cursor-pointer p-1.5 rounded-lg hover:bg-white/5">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>
            </div>
            {{-- AI Quick-Entry --}}
            <div class="mb-6 rounded-xl border border-[#00E676]/20 p-4" style="background:rgba(0,230,118,0.03);">
                <div class="flex items-center gap-2 mb-2">
                    <span class="material-symbols-outlined text-[16px]" style="color:#00E676;">auto_awesome</span>
                    <p class="text-[10px] font-mono font-bold uppercase tracking-widest" style="color:#00E676;">AI Quick
                        Entry</p>
                </div>
                <p class="font-mono text-[10px] text-on-surface-variant/50 mb-3">
                    Describe the match in plain words — AI fills the score &amp; events. You review before saving.
                </p>

                <textarea wire:model="aiSummary" rows="3"
                    placeholder="e.g. Team 4 won 2-1. Hakeem scored at 20', Uche added another. Tobi got Team 5's goal. Musa Okafor booked."
                    class="w-full rounded-xl text-sm font-mono outline-none transition-all p-3 mb-3"
                    style="color:#fff; background:rgba(255,255,255,0.06); border:2px solid rgba(255,255,255,0.15);"></textarea>

                <button wire:click="parseResult" wire:loading.attr="disabled" wire:target="parseResult"
                    class="px-5 py-2.5 rounded-xl text-xs font-mono font-bold uppercase tracking-wider text-black transition-all cursor-pointer disabled:opacity-50"
                    style="background:linear-gradient(135deg,#00E676 0%,#00b359 100%);">
                    <span wire:loading.remove wire:target="parseResult">✨ Parse with AI</span>
                    <span wire:loading wire:target="parseResult">Reading…</span>
                </button>

                {{-- AI message --}}
                @if($aiMessage)
                    <p class="font-mono text-[10px] text-on-surface-variant/70 mt-3">{{ $aiMessage }}</p>
                @endif

                {{-- Warnings — the ambiguous/unmatched items --}}
                @if(count($aiWarnings) > 0)
                    <div class="mt-3 rounded-lg border border-amber-500/25 p-3" style="background:rgba(253,212,0,0.04);">
                        <p class="font-mono text-[9px] font-bold uppercase tracking-widest text-amber-400 mb-1.5">
                            Needs your attention — add these manually below
                        </p>
                        <ul class="space-y-1">
                            @foreach($aiWarnings as $w)
                                <li class="font-mono text-[10px] text-on-surface-variant/70 flex items-start gap-1.5">
                                    <span class="text-amber-400 flex-shrink-0">•</span>
                                    <span>{{ $w }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
            <div class="p-4 sm:p-6">

                {{-- Two-column layout on large screens --}}
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

                    {{-- ── Left col: Score + Status ── --}}
                    <div>
                        <p class="text-[9px] font-mono font-bold uppercase tracking-widest text-on-surface-variant/40 mb-4">
                            Match Result</p>

                        {{-- Score inputs --}}
                        <div class="flex items-center gap-4 mb-5">
                            <div class="flex-1">
                                <label class="block text-[10px] font-mono text-on-surface-variant/60 mb-2 truncate">
                                    {{ $selectedFixture['home_team']['name'] ?? 'Home' }}
                                </label>
                                <input wire:model="home_score" type="number" min="0" max="99" placeholder="0"
                                    class="w-full rounded-xl text-3xl font-black text-center font-mono outline-none transition-all"
                                    style="padding:14px 8px; color:#fff; background:rgba(255,255,255,0.08); border:2px solid rgba(255,255,255,0.2);"
                                    onfocus="this.style.borderColor='#00E676'; this.style.background='rgba(0,230,118,0.08)';"
                                    onblur="this.style.borderColor='rgba(255,255,255,0.2)'; this.style.background='rgba(255,255,255,0.08)';" />
                                @error('home_score') <p class="text-red-400 text-[10px] font-mono mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="text-on-surface-variant/25 text-3xl font-black font-mono mt-5">—</div>

                            <div class="flex-1">
                                <label class="block text-[10px] font-mono text-on-surface-variant/60 mb-2 truncate">
                                    {{ $selectedFixture['away_team']['name'] ?? 'Away' }}
                                </label>
                                <input wire:model="away_score" type="number" min="0" max="99" placeholder="0"
                                    class="w-full rounded-xl text-3xl font-black text-center font-mono outline-none transition-all"
                                    style="padding:14px 8px; color:#fff; background:rgba(255,255,255,0.08); border:2px solid rgba(255,255,255,0.2);"
                                    onfocus="this.style.borderColor='#00E676'; this.style.background='rgba(0,230,118,0.08)';"
                                    onblur="this.style.borderColor='rgba(255,255,255,0.2)'; this.style.background='rgba(255,255,255,0.08)';" />
                                @error('away_score') <p class="text-red-400 text-[10px] font-mono mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        {{-- Status --}}
                        <div>
                            <label
                                class="block text-[10px] font-mono text-on-surface-variant/60 mb-2 uppercase tracking-wider">Match
                                Status</label>
                            <select wire:model="fixture_status"
                                class="w-full rounded-xl text-sm font-mono outline-none transition-all appearance-none cursor-pointer"
                                style="padding:11px 16px; color:#fff; background:rgba(255,255,255,0.08); border:2px solid rgba(255,255,255,0.2);"
                                onfocus="this.style.borderColor='#00E676';"
                                onblur="this.style.borderColor='rgba(255,255,255,0.2)';">
                                @foreach(\App\Enums\FixtureStatus::cases() as $case)
                                    <option value="{{ $case->value }}" style="background:#0d110f;">{{ $case->label() }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Events log (shown in left col on desktop) --}}
                        @if(count($events) > 0)
                            <div class="mt-6">
                                <p
                                    class="text-[9px] font-mono font-bold uppercase tracking-widest text-on-surface-variant/40 mb-3">
                                    Events <span class="opacity-60">({{ count($events) }})</span>
                                </p>
                                <div class="space-y-1.5">
                                    @foreach($events as $i => $event)
                                        <div class="flex items-center justify-between px-3 py-2 rounded-lg border border-outline-variant/15"
                                            style="background:rgba(255,255,255,0.02);">
                                            <div class="flex items-center gap-2.5 min-w-0">
                                                <span
                                                    class="font-mono text-[10px] text-on-surface-variant/40 w-7 flex-shrink-0 tabular-nums">
                                                    {{ $event['minute'] ? $event['minute'] . "'" : '—' }}
                                                </span>
                                                <span class="font-mono text-[9px] font-bold flex-shrink-0 uppercase"
                                                    style="{{ $this->eventStyle($event['event_type']) }}">
                                                    {{ str_replace('_', ' ', $event['event_type']) }}
                                                </span>
                                                <span class="text-xs text-on-surface truncate">{{ $event['player_name'] }}</span>
                                                @if($event['is_substitute'])
                                                    <span
                                                        class="font-mono text-[9px] text-on-surface-variant/30 border border-outline-variant/20 px-1 py-0.5 rounded flex-shrink-0">SUB</span>
                                                @endif
                                            </div>
                                            <button wire:click="removeEvent({{ $i }})"
                                                class="text-on-surface-variant/25 hover:text-red-400 transition-colors cursor-pointer flex-shrink-0 ml-2">
                                                <span class="material-symbols-outlined text-[14px]">close</span>
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- ── Right col: Log Player Event ── --}}
                    <div>
                        <p class="text-[9px] font-mono font-bold uppercase tracking-widest text-on-surface-variant/40 mb-4">
                            Log Player Event</p>

                        {{-- Player --}}
                        <div class="mb-4">
                            <label class="block text-[10px] font-mono text-on-surface-variant/60 mb-2">Player</label>
                            <select wire:model="event_player_id"
                                class="w-full rounded-xl text-sm font-mono outline-none transition-all appearance-none cursor-pointer"
                                style="padding:11px 16px; color:#fff; background:rgba(255,255,255,0.08); border:2px solid rgba(255,255,255,0.2);"
                                onfocus="this.style.borderColor='#00E676';"
                                onblur="this.style.borderColor='rgba(255,255,255,0.2)';">
                                <option value="" style="background:#0d110f;">— Select player —</option>
                                <optgroup label="{{ $selectedFixture['home_team']['name'] ?? 'Home' }}"
                                    style="background:#0d110f;">
                                    @foreach($homePlayers as $p)
                                        <option value="{{ $p['id'] }}" style="background:#0d110f;">
                                            [{{ $p['position'] ?? 'N/A' }}] {{ $p['name'] }}</option>
                                    @endforeach
                                </optgroup>
                                <optgroup label="{{ $selectedFixture['away_team']['name'] ?? 'Away' }}"
                                    style="background:#0d110f;">
                                    @foreach($awayPlayers as $p)
                                        <option value="{{ $p['id'] }}" style="background:#0d110f;">
                                            [{{ $p['position'] ?? 'N/A' }}] {{ $p['name'] }}</option>
                                    @endforeach
                                </optgroup>
                            </select>
                            @error('event_player_id') <p class="text-red-400 text-[10px] font-mono mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Event type + Minute --}}
                        <div class="grid grid-cols-2 gap-3 mb-4">
                            <div>
                                <label class="block text-[10px] font-mono text-on-surface-variant/60 mb-2">Event
                                    Type</label>
                                <select wire:model="event_type"
                                    class="w-full rounded-xl text-sm font-mono outline-none transition-all appearance-none cursor-pointer"
                                    style="padding:11px 16px; color:#fff; background:rgba(255,255,255,0.08); border:2px solid rgba(255,255,255,0.2);"
                                    onfocus="this.style.borderColor='#00E676';"
                                    onblur="this.style.borderColor='rgba(255,255,255,0.2)';">
                                    <option value="goal" style="background:#0d110f;">⚽ Goal</option>
                                    <option value="assist" style="background:#0d110f;">🅰 Assist</option>
                                    <option value="yellow" style="background:#0d110f;">🟨 Yellow Card</option>
                                    <option value="red" style="background:#0d110f;">🟥 Red Card</option>
                                    <option value="own_goal" style="background:#0d110f;">⚽ Own Goal</option>
                                    <option value="penalty_saved" style="background:#0d110f;">🧤 Penalty Saved</option>
                                    <option value="penalty_miss" style="background:#0d110f;">❌ Penalty Miss</option>
                                    <option value="sub_on" style="background:#0d110f;">⬆ Sub On</option>
                                    <option value="sub_off" style="background:#0d110f;">⬇ Sub Off</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-mono text-on-surface-variant/60 mb-2">Minute</label>
                                <input wire:model="event_minute" type="number" min="1" max="120" placeholder="e.g. 45"
                                    class="w-full rounded-xl text-sm font-mono outline-none transition-all"
                                    style="padding:11px 16px; color:#fff; background:rgba(255,255,255,0.08); border:2px solid rgba(255,255,255,0.2);"
                                    onfocus="this.style.borderColor='#00E676'; this.style.background='rgba(0,230,118,0.06)';"
                                    onblur="this.style.borderColor='rgba(255,255,255,0.2)'; this.style.background='rgba(255,255,255,0.08)';" />
                            </div>
                        </div>

                        {{-- Sub checkbox + Add button --}}
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <label class="flex items-center gap-2.5 cursor-pointer">
                                <input wire:model="event_is_substitute" type="checkbox"
                                    class="w-4 h-4 rounded accent-[#00E676] cursor-pointer flex-shrink-0" />
                                <span class="font-mono text-xs text-on-surface-variant/60">Came on as sub</span>
                            </label>
                            <button wire:click="addEvent"
                                class="w-full sm:w-auto px-6 py-2.5 rounded-xl text-xs font-mono font-bold uppercase tracking-wider text-black transition-all cursor-pointer hover:opacity-90"
                                style="background:linear-gradient(135deg,#00E676 0%,#00b359 100%);">
                                + Add Event
                            </button>
                        </div>
                    </div>
                </div>

            </div>

            {{-- ── Scoring Preview ─────────────────────────────────────────── --}}
            @if(count($events) > 0 || (is_numeric($home_score) && is_numeric($away_score)))
                <div class="mx-4 sm:mx-6 mb-6 rounded-xl border border-outline-variant/20 overflow-hidden"
                    style="background:rgba(255,255,255,0.02);">
                    <div class="px-4 py-3 border-b border-outline-variant/15" style="background:rgba(0,230,118,0.03);">
                        <p class="text-[9px] font-mono font-bold uppercase tracking-widest text-on-surface-variant/50">
                            Scoring Preview <span class="normal-case font-normal opacity-60 ml-1">· updates as you log
                                events</span>
                        </p>
                    </div>
                    @php
                        $goalEvents = collect($events)->where('event_type', 'goal')->sortBy('minute')->values();
                        $assistEvents = collect($events)->where('event_type', 'assist')->values();
                        $yellowEvents = collect($events)->where('event_type', 'yellow')->values();
                        $redEvents = collect($events)->where('event_type', 'red')->values();
                        $penSaveEvents = collect($events)->where('event_type', 'penalty_saved')->values();
                        $firstGoal = $goalEvents->first();
                        $homeClean = is_numeric($home_score) && is_numeric($away_score) && (int) $away_score === 0;
                        $awayClean = is_numeric($home_score) && is_numeric($away_score) && (int) $home_score === 0;
                    @endphp
                    <div class="divide-y divide-outline-variant/10">

                        {{-- First Goalscorer --}}
                        <div class="flex items-start gap-3 px-4 py-2.5">
                            <span class="text-sm flex-shrink-0 mt-0.5">⚽</span>
                            <div class="flex-1 min-w-0">
                                <span
                                    class="font-mono text-[9px] font-bold uppercase tracking-widest text-on-surface-variant/50 block">First
                                    Goalscorer</span>
                                @if($firstGoal)
                                    <span class="text-xs text-on-surface font-bold">{{ $firstGoal['player_name'] }}</span>
                                    @if($firstGoal['minute'])<span
                                    class="font-mono text-[10px] text-on-surface-variant/50 ml-1">({{ $firstGoal['minute'] }}')</span>@endif
                                    <span class="font-mono text-[9px] text-[#a78bfa] ml-2">+4 pts prediction</span>
                                @else
                                    <span class="font-mono text-[10px] text-on-surface-variant/30">None logged</span>
                                @endif
                            </div>
                        </div>

                        {{-- Assists --}}
                        <div class="flex items-start gap-3 px-4 py-2.5">
                            <span class="text-sm flex-shrink-0 mt-0.5">🅰</span>
                            <div class="flex-1 min-w-0">
                                <span
                                    class="font-mono text-[9px] font-bold uppercase tracking-widest text-on-surface-variant/50 block">Assists</span>
                                @forelse($assistEvents as $e)
                                    <span
                                        class="inline-block text-xs text-on-surface mr-3">{{ $e['player_name'] }}@if($e['minute'])<span
                                            class="font-mono text-[9px] text-on-surface-variant/40">
                                        ({{ $e['minute'] }}')</span>@endif <span class="font-mono text-[9px] text-[#a78bfa]">+3
                                            fantasy</span></span>
                                @empty
                                    <span class="font-mono text-[10px] text-on-surface-variant/30">None logged</span>
                                @endforelse
                            </div>
                        </div>

                        {{-- Yellow Cards --}}
                        <div class="flex items-start gap-3 px-4 py-2.5">
                            <span class="text-sm flex-shrink-0 mt-0.5">🟨</span>
                            <div class="flex-1 min-w-0">
                                <span
                                    class="font-mono text-[9px] font-bold uppercase tracking-widest text-on-surface-variant/50 block">Yellow
                                    Cards</span>
                                @forelse($yellowEvents as $e)
                                    <span class="inline-block text-xs text-on-surface mr-3">{{ $e['player_name'] }} <span
                                            class="font-mono text-[9px] text-red-400">−1 fantasy</span> <span
                                            class="font-mono text-[9px] text-[#a78bfa]">· scores CardedPlayer</span></span>
                                @empty
                                    <span class="font-mono text-[10px] text-on-surface-variant/30">None</span>
                                @endforelse
                            </div>
                        </div>

                        {{-- Red Cards --}}
                        <div class="flex items-start gap-3 px-4 py-2.5">
                            <span class="text-sm flex-shrink-0 mt-0.5">🟥</span>
                            <div class="flex-1 min-w-0">
                                <span
                                    class="font-mono text-[9px] font-bold uppercase tracking-widest text-on-surface-variant/50 block">Red
                                    Cards</span>
                                @forelse($redEvents as $e)
                                    <span class="inline-block text-xs text-on-surface mr-3">{{ $e['player_name'] }} <span
                                            class="font-mono text-[9px] text-red-400">−3 fantasy</span> <span
                                            class="font-mono text-[9px] text-[#a78bfa]">· scores CardedPlayer</span></span>
                                @empty
                                    <span class="font-mono text-[10px] text-on-surface-variant/30">None</span>
                                @endforelse
                            </div>
                        </div>

                        {{-- Penalty Saved --}}
                        <div class="flex items-start gap-3 px-4 py-2.5">
                            <span class="text-sm flex-shrink-0 mt-0.5">🧤</span>
                            <div class="flex-1 min-w-0">
                                <span
                                    class="font-mono text-[9px] font-bold uppercase tracking-widest text-on-surface-variant/50 block">Penalty
                                    Saved</span>
                                @forelse($penSaveEvents as $e)
                                    <span class="inline-block text-xs text-on-surface mr-3">{{ $e['player_name'] }} <span
                                            class="font-mono text-[9px] text-[#00E676]">+5 fantasy</span></span>
                                @empty
                                    <span class="font-mono text-[10px] text-on-surface-variant/30">None</span>
                                @endforelse
                            </div>
                        </div>

                        {{-- Clean Sheet --}}
                        <div class="flex items-start gap-3 px-4 py-2.5">
                            <span class="text-sm flex-shrink-0 mt-0.5">🛡</span>
                            <div class="flex-1 min-w-0">
                                <span
                                    class="font-mono text-[9px] font-bold uppercase tracking-widest text-on-surface-variant/50 block">Clean
                                    Sheet</span>
                                @if(is_numeric($home_score) && is_numeric($away_score))
                                    <span class="font-mono text-xs mr-4">
                                        <span class="text-on-surface-variant/70">{{ $selectedFixture['home_team']['name'] }}:</span>
                                        @if($homeClean)<span style="color:#00E676;">✓</span> <span
                                        class="font-mono text-[9px] text-[#00E676]">+4 pts GK/DEF (60+ min)</span>@else<span
                                            class="text-red-400/70">✗</span>@endif
                                    </span>
                                    <span class="font-mono text-xs">
                                        <span class="text-on-surface-variant/70">{{ $selectedFixture['away_team']['name'] }}:</span>
                                        @if($awayClean)<span style="color:#00E676;">✓</span> <span
                                        class="font-mono text-[9px] text-[#00E676]">+4 pts GK/DEF (60+ min)</span>@else<span
                                            class="text-red-400/70">✗</span>@endif
                                    </span>
                                @else
                                    <span class="font-mono text-[10px] text-on-surface-variant/30">Enter scores above to see clean
                                        sheet status</span>
                                @endif
                            </div>
                        </div>

                    </div>
                </div>
            @endif

            {{-- LINEUP — minutes + saves per player --}}
            <div class="px-4 sm:px-6 pb-4">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-xs font-mono text-on-surface-variant uppercase tracking-wider">
                        Lineup <span class="text-on-surface-variant/40">(minutes played · saves for GK)</span>
                    </p>
                    <div class="flex gap-2">
                        <button wire:click="setAllMinutes(90)"
                            class="px-2.5 py-1 rounded-lg font-mono text-[10px] font-bold border border-outline-variant/20 bg-white/5 text-on-surface-variant hover:text-[#00E676] hover:border-[#00E676]/30 transition-all cursor-pointer">
                            All 90 min
                        </button>
                        <button wire:click="setAllMinutes(0)"
                            class="px-2.5 py-1 rounded-lg font-mono text-[10px] font-bold border border-outline-variant/20 bg-white/5 text-on-surface-variant hover:text-red-400 hover:border-red-400/30 transition-all cursor-pointer">
                            Clear all
                        </button>
                    </div>
                </div>

                @foreach([['label' => $selectedFixture['home_team']['name'], 'players' => $homePlayers], ['label' => $selectedFixture['away_team']['name'], 'players' => $awayPlayers]] as $team)
                    <div class="mb-4">
                        <p class="font-mono text-[10px] text-on-surface-variant/60 uppercase tracking-widest mb-2">
                            {{ $team['label'] }}
                        </p>
                        <div class="space-y-1.5">
                            @foreach($team['players'] as $p)
                                <div class="flex items-center gap-3 px-3 py-2 rounded-xl border border-outline-variant/15"
                                    style="background: rgba(255,255,255,0.02);">

                                    {{-- Position + name --}}
                                    <div class="flex items-center gap-2 flex-1 min-w-0">
                                        <span
                                            class="font-mono text-[9px] font-black uppercase px-1.5 py-0.5 rounded flex-shrink-0
                                                                                                                                                                                                     bg-white/5 text-on-surface-variant/70">
                                            {{ $p['position'] }}
                                        </span>
                                        <span class="text-sm text-white truncate">{{ $p['name'] }}</span>
                                    </div>

                                    {{-- Minutes --}}
                                    <div class="flex items-center gap-1.5 flex-shrink-0">
                                        <input type="number" min="0" max="120" wire:model="lineup.{{ $p['id'] }}.minutes"
                                            placeholder="0"
                                            class="w-16 px-2 py-1.5 rounded-lg text-sm text-center text-white border border-outline-variant/20 bg-white/5 focus:outline-none focus:border-[#00E676]/50 transition-all font-mono" />
                                        <span class="font-mono text-[9px] text-on-surface-variant/40">min</span>
                                    </div>

                                    {{-- Saves (GK only) --}}
                                    @if($p['position'] === 'GK')
                                        <div class="flex items-center gap-1.5 flex-shrink-0">
                                            <input type="number" min="0" max="50" wire:model="lineup.{{ $p['id'] }}.saves"
                                                placeholder="0"
                                                class="w-16 px-2 py-1.5 rounded-lg text-sm text-center text-white border border-outline-variant/20 bg-white/5 focus:outline-none focus:border-[#00E676]/50 transition-all font-mono" />
                                            <span class="font-mono text-[9px] text-on-surface-variant/40">saves</span>
                                        </div>
                                    @else
                                        <div class="w-[88px] flex-shrink-0"></div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
            {{-- TOP PERFORMERS — bonus 3/2/1 (AI suggests, admin overrides) --}}
            <div class="px-4 sm:px-6 pb-4">
                <div class="rounded-xl border border-[#00E676]/20 p-4" style="background:rgba(0,230,118,0.03);">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="material-symbols-outlined text-[16px]" style="color:#00E676;">workspace_premium</span>
                        <p class="text-[10px] font-mono font-bold uppercase tracking-widest" style="color:#00E676;">Top
                            Performers · Bonus</p>
                    </div>
                    <p class="font-mono text-[10px] text-on-surface-variant/50 mb-3">
                        Best 3 players get bonus points (3 / 2 / 1). AI suggests — change any below.
                    </p>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        @foreach([0 => ['1st', 3], 1 => ['2nd', 2], 2 => ['3rd', 1]] as $rank => $meta)
                            <div>
                                <label class="block text-[10px] font-mono text-on-surface-variant/60 mb-1.5">
                                    {{ $meta[0] }} <span style="color:#00E676;">+{{ $meta[1] }}</span>
                                </label>
                                <select wire:model="topPerformers.{{ $rank }}"
                                    class="w-full rounded-xl text-xs font-mono outline-none p-2.5 cursor-pointer text-white"
                                    style="background:rgba(255,255,255,0.06); border:2px solid rgba(255,255,255,0.15);">
                                    <option value="" style="background:#0d110f;">— None —</option>
                                    <optgroup label="{{ $selectedFixture['home_team']['name'] ?? 'Home' }}"
                                        style="background:#0d110f;">
                                        @foreach($homePlayers as $p)
                                            <option value="{{ $p['id'] }}" style="background:#0d110f;">{{ $p['name'] }}</option>
                                        @endforeach
                                    </optgroup>
                                    <optgroup label="{{ $selectedFixture['away_team']['name'] ?? 'Away' }}"
                                        style="background:#0d110f;">
                                        @foreach($awayPlayers as $p)
                                            <option value="{{ $p['id'] }}" style="background:#0d110f;">{{ $p['name'] }}</option>
                                        @endforeach
                                    </optgroup>
                                </select>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
            {{-- Panel footer --}}
            <div class="px-4 sm:px-6 py-4 border-t border-outline-variant/15 flex items-center justify-end gap-3"
                style="background:rgba(255,255,255,0.01);">
                <button wire:click="closePanel"
                    class="px-5 py-2.5 rounded-xl text-xs font-mono font-bold uppercase tracking-wider border transition-all cursor-pointer hover:bg-white/5"
                    style="color:rgba(255,255,255,0.5); border-color:rgba(255,255,255,0.15);">
                    Cancel
                </button>
                <button wire:click="saveResult" wire:loading.attr="disabled" wire:target="saveResult"
                    class="px-6 py-2.5 rounded-xl text-xs font-mono font-bold uppercase tracking-wider text-black transition-all cursor-pointer disabled:opacity-50"
                    style="background:linear-gradient(135deg,#00E676 0%,#00b359 100%);">
                    <span wire:loading.remove wire:target="saveResult">Save Result</span>
                    <span wire:loading wire:target="saveResult">Saving...</span>
                </button>
            </div>

        </div>
    @endif

</div>