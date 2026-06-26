<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Lazy;
use App\Models\PlayerEvent;
use App\Models\FixturePlayerStat;
use App\Models\Tournament;
use Illuminate\Support\Facades\DB;

new #[Layout('layouts.app'), Lazy] class extends Component {

    public string $tab = 'scorers';   // 'scorers' | 'assists' | 'clean_sheets'
    public ?int $tournamentId = null;
    public $tournaments = [];

    public function placeholder(): string
    {
        return <<<'HTML'
        <div class="max-w-4xl mx-auto px-5 sm:px-8 py-10">
            <div class="h-8 shimmer rounded-xl w-48 mb-8"></div>
            <div class="flex gap-2 mb-6">
                <div class="h-10 shimmer rounded-xl w-28"></div>
                <div class="h-10 shimmer rounded-xl w-28"></div>
                <div class="h-10 shimmer rounded-xl w-28"></div>
            </div>
            <div class="h-14 shimmer rounded-xl mb-2"></div>
            <div class="h-14 shimmer rounded-xl mb-2"></div>
            <div class="h-14 shimmer rounded-xl mb-2"></div>
            <div class="h-14 shimmer rounded-xl"></div>
        </div>
        HTML;
    }

    public function mount(): void
    {
        $this->tournaments = Tournament::orderByRaw("FIELD(status, 'active', 'upcoming', 'completed')")
            ->get(['id', 'name'])
            ->toArray();

        // Default to the active tournament (or the first one)
        $this->tournamentId = (int) (request()->integer('tournament')
            ?: Tournament::where('status', 'active')->value('id')
            ?: ($this->tournaments[0]['id'] ?? 0)) ?: null;
    }

    // ─── Top scorers: count 'goal' events in this tournament's fixtures ──────
    public function scorers(): array
    {
        if (!$this->tournamentId) return [];

        return $this->eventCounts('goal', 'goals');
    }

    // ─── Top assists: count 'assist' events ─────────────────────────────────
    public function assists(): array
    {
        if (!$this->tournamentId) return [];

        return $this->eventCounts('assist', 'assists');
    }

    /** Shared counter for goal/assist player_events scoped to the tournament. */
    private function eventCounts(string $eventType, string $alias): array
    {
        return PlayerEvent::query()
            ->join('players', 'player_events.player_id', '=', 'players.id')
            ->join('fixtures', 'player_events.fixture_id', '=', 'fixtures.id')
            ->join('teams', 'players.team_id', '=', 'teams.id')
            ->where('fixtures.tournament_id', $this->tournamentId)
            ->where('player_events.event_type', $eventType)
            ->groupBy('players.id', 'players.name', 'teams.name')
            ->select('players.name as player', 'teams.name as team', DB::raw('COUNT(*) as count'))
            ->orderByDesc('count')
            ->orderBy('players.name')
            ->limit(50)
            ->get()
            ->map(fn($r) => ['player' => $r->player, 'team' => $r->team, 'count' => (int) $r->count])
            ->toArray();
    }

    // ─── Clean sheets (GK & DEF only) ───────────────────────────────────────
    // Matches ScoreFixtureJob: a player is credited when they played 60+ minutes
    // in a COMPLETED fixture where their team conceded 0 (opponent score = 0).
    public function cleanSheets(): array
    {
        if (!$this->tournamentId) return [];

        return FixturePlayerStat::query()
            ->join('players', 'fixture_player_stats.player_id', '=', 'players.id')
            ->join('fixtures', 'fixture_player_stats.fixture_id', '=', 'fixtures.id')
            ->join('teams', 'players.team_id', '=', 'teams.id')
            ->where('fixtures.tournament_id', $this->tournamentId)
            ->where('fixtures.status', 'completed')
            ->where('fixture_player_stats.minutes_played', '>=', 60)
            ->whereIn('players.position', ['GK', 'DEF'])
            ->where(function ($q) {
                // player's team is HOME and away conceded 0...
                $q->where(function ($q) {
                    $q->whereColumn('players.team_id', 'fixtures.home_team_id')
                      ->where('fixtures.away_score', 0);
                })
                // ...or player's team is AWAY and home scored 0
                ->orWhere(function ($q) {
                    $q->whereColumn('players.team_id', 'fixtures.away_team_id')
                      ->where('fixtures.home_score', 0);
                });
            })
            ->groupBy('players.id', 'players.name', 'players.position', 'teams.name')
            ->select(
                'players.name as player',
                'players.position as position',
                'teams.name as team',
                DB::raw('COUNT(*) as count')
            )
            ->orderByDesc('count')
            ->orderBy('players.name')
            ->limit(50)
            ->get()
            ->map(fn($r) => ['player' => $r->player, 'team' => $r->team, 'position' => $r->position, 'count' => (int) $r->count])
            ->toArray();
    }
}; ?>

<div class="max-w-4xl mx-auto px-5 sm:px-8 py-10 space-y-6">

    {{-- Back --}}
    <button type="button" onclick="window.history.back()"
        class="inline-flex items-center gap-1.5 font-mono text-[11px] text-on-surface-variant/60 hover:text-[#00E676] transition-colors cursor-pointer">
        <span class="material-symbols-outlined text-[16px]">arrow_back</span>
        Back
    </button>

    {{-- Header --}}
    <div class="text-center mb-4">
        <h1 class="font-display font-black text-3xl sm:text-4xl text-white tracking-tight mb-2">
            Player <span style="color:#00E676;">Stats</span>
        </h1>
        <p class="font-mono text-[10px] uppercase tracking-widest text-on-surface-variant/60">Top scorers, assists & clean sheets</p>
    </div>

    {{-- Tournament selector --}}
    @if(count($tournaments) > 0)
        <div class="flex justify-center">
            <select wire:model.live="tournamentId"
                class="px-4 py-2.5 rounded-xl text-sm text-white border border-outline-variant/20 bg-[#0d110f] focus:outline-none focus:border-[#00E676]/50 transition-all appearance-none cursor-pointer">
                @foreach($tournaments as $t)
                    <option value="{{ $t['id'] }}">{{ $t['name'] }}</option>
                @endforeach
            </select>
        </div>
    @endif

    {{-- Tabs --}}
    <div class="flex gap-2 justify-center p-1 rounded-xl bg-white/[0.03] border border-outline-variant/15 w-fit mx-auto">
        @foreach(['scorers' => 'Top Scorers', 'assists' => 'Assists', 'clean_sheets' => 'Clean Sheets'] as $val => $label)
            <button wire:click="$set('tab', '{{ $val }}')"
                class="px-4 sm:px-5 py-2 rounded-lg font-mono text-xs font-bold uppercase tracking-wider transition-all
                       {{ $tab === $val ? 'text-black' : 'text-on-surface-variant hover:text-white' }}"
                style="{{ $tab === $val ? 'background:#00E676;' : '' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- ═══ SCORERS / ASSISTS ═══ --}}
    @if($tab === 'scorers' || $tab === 'assists')
        @php
            $rows = $tab === 'scorers' ? $this->scorers() : $this->assists();
            $metric = $tab === 'scorers' ? 'Goals' : 'Assists';
        @endphp
        <div class="rounded-2xl border border-outline-variant/15 overflow-hidden" style="background: rgba(13,17,15,0.8);">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[360px]">
                    <thead>
                        <tr class="border-b border-outline-variant/15 font-mono text-xs uppercase tracking-wider text-on-surface-variant/60"
                            style="background: rgba(255,255,255,0.02);">
                            <th class="py-3.5 px-5 w-16">#</th>
                            <th class="py-3.5 px-5">Player</th>
                            <th class="py-3.5 px-5">Team</th>
                            <th class="py-3.5 px-5 text-right">{{ $metric }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/10 text-sm">
                        @forelse($rows as $i => $row)
                            <tr class="hover:bg-white/[0.02] transition-colors">
                                <td class="py-4 px-5 font-mono font-bold {{ $i < 3 ? '' : 'text-on-surface-variant/60' }}"
                                    style="{{ $i < 3 ? 'color:#00E676;' : '' }}">{{ $i + 1 }}</td>
                                <td class="py-4 px-5 font-bold text-white">{{ $row['player'] }}</td>
                                <td class="py-4 px-5 text-on-surface-variant/70 font-mono text-xs">{{ $row['team'] }}</td>
                                <td class="py-4 px-5 text-right font-mono font-bold" style="color:#00E676;">{{ $row['count'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-12 text-center font-mono text-xs text-on-surface-variant/40">
                                    No {{ strtolower($metric) }} recorded yet for this tournament.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- ═══ CLEAN SHEETS ═══ --}}
    @if($tab === 'clean_sheets')
        @php $rows = $this->cleanSheets(); @endphp
        <div class="rounded-2xl border border-outline-variant/15 overflow-hidden" style="background: rgba(13,17,15,0.8);">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[360px]">
                    <thead>
                        <tr class="border-b border-outline-variant/15 font-mono text-xs uppercase tracking-wider text-on-surface-variant/60"
                            style="background: rgba(255,255,255,0.02);">
                            <th class="py-3.5 px-5 w-16">#</th>
                            <th class="py-3.5 px-5">Player</th>
                            <th class="py-3.5 px-5">Pos</th>
                            <th class="py-3.5 px-5">Team</th>
                            <th class="py-3.5 px-5 text-right">Clean Sheets</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/10 text-sm">
                        @forelse($rows as $i => $row)
                            <tr class="hover:bg-white/[0.02] transition-colors">
                                <td class="py-4 px-5 font-mono font-bold {{ $i < 3 ? '' : 'text-on-surface-variant/60' }}"
                                    style="{{ $i < 3 ? 'color:#00E676;' : '' }}">{{ $i + 1 }}</td>
                                <td class="py-4 px-5 font-bold text-white">{{ $row['player'] }}</td>
                                <td class="py-4 px-5 font-mono text-[10px] font-bold uppercase text-on-surface-variant/60">{{ $row['position'] }}</td>
                                <td class="py-4 px-5 text-on-surface-variant/70 font-mono text-xs">{{ $row['team'] }}</td>
                                <td class="py-4 px-5 text-right font-mono font-bold" style="color:#00E676;">{{ $row['count'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-12 text-center font-mono text-xs text-on-surface-variant/40">
                                    No clean sheets recorded yet for this tournament.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <p class="text-center font-mono text-[10px] text-on-surface-variant/40">
            Clean sheet = goalkeeper/defender who played 60+ minutes in a completed match their team won or drew without conceding.
        </p>
    @endif

    @push('ads')
        @include('partials.propeller-ad')
    @endpush
</div>
