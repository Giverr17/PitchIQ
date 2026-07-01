<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\On;
use App\Models\FantasyTeam;
use App\Models\Fixture;
use App\Models\Tournament;
use App\Models\User;
use App\Models\Prediction;
use Illuminate\Support\Facades\DB;

new #[Layout('layouts.app'), Lazy] class extends Component {

    public string $tab = 'fantasy';       // 'fantasy' | 'predictions' | 'faculty'
    public ?int $tournamentId = null;
    public $tournaments = [];

    public ?int $selectedMatchday = null;  // null = overall/season
    public array $availableMatchdays = [];

    public function placeholder(): string
    {
        return <<<'HTML'
        <div class="max-w-5xl mx-auto px-5 sm:px-8 py-10">
            <div class="h-8 shimmer rounded-xl w-48 mb-8"></div>
            <div class="flex gap-2 mb-6">
                <div class="h-10 shimmer rounded-xl w-28"></div>
                <div class="h-10 shimmer rounded-xl w-28"></div>
                <div class="h-10 shimmer rounded-xl w-28"></div>
            </div>
            <div class="h-14 shimmer rounded-xl mb-2"></div>
            <div class="h-14 shimmer rounded-xl mb-2"></div>
            <div class="h-14 shimmer rounded-xl mb-2"></div>
            <div class="h-14 shimmer rounded-xl mb-2"></div>
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

        // Default to the active (or first) tournament
        $this->tournamentId = Tournament::where('status', 'active')->value('id')
            ?? ($this->tournaments[0]['id'] ?? null);

        $this->loadAvailableMatchdays();
    }

    public function updatedTournamentId(): void
    {
        $this->loadAvailableMatchdays();
        $this->selectedMatchday = null;
    }

    private function loadAvailableMatchdays(): void
    {
        if (!$this->tournamentId) {
            $this->availableMatchdays = [];
            return;
        }

        $this->availableMatchdays = Fixture::where('tournament_id', $this->tournamentId)
            ->distinct()
            ->orderBy('matchday')
            ->pluck('matchday')
            ->toArray();
    }

    // ─── Live update hook (Part B will use this) ─────────────────────────
    #[On('echo:leaderboard,LeaderboardUpdated')]
    public function refreshLeaderboard(): void
    {
        // Method body can stay empty — being called triggers a re-render,
        // and the computed methods below re-query fresh data.
    }

    // ─── Overall standings: fantasy + verified-prediction points combined ─────
    // Mirrors the dashboard/My-Stats calculation so a user's rank is consistent
    // everywhere: squad-builder points and prediction points accumulate as ONE total.
    public function fantasyStandings(): array
    {
        if (!$this->tournamentId)
            return [];

        $tid = $this->tournamentId;
        $md  = $this->selectedMatchday;   // null = whole season

        // Fantasy points per user (optionally scoped to a matchday via its fixture)
        $fantasy = FantasyTeam::where('tournament_id', $tid)
            ->when($md !== null, fn($q) => $q->whereHas('fixture', fn($f) => $f->where('matchday', $md)))
            ->selectRaw('user_id, SUM(total_points) as pts')
            ->groupBy('user_id')
            ->pluck('pts', 'user_id');

        // Verified prediction points per user (same tournament + matchday scope)
        $preds = Prediction::whereHas(
                'fixture',
                fn($f) => $f->where('tournament_id', $tid)->when($md !== null, fn($q) => $q->where('matchday', $md))
            )
            ->whereNotNull('verified_at')
            ->selectRaw('user_id, SUM(points_earned) as pts')
            ->groupBy('user_id')
            ->pluck('pts', 'user_id');

        $uids = $fantasy->keys()->merge($preds->keys())->unique();
        if ($uids->isEmpty())
            return [];

        $users = User::whereIn('id', $uids)->get(['id', 'name', 'faculty'])->keyBy('id');

        return $uids->map(fn($uid) => [
                'manager'           => $users[$uid]->name ?? '—',
                'faculty'           => $users[$uid]->faculty ?? '—',
                'fantasy_points'    => (int) ($fantasy[$uid] ?? 0),
                'prediction_points' => (int) ($preds[$uid] ?? 0),
                'total_points'      => (int) ($fantasy[$uid] ?? 0) + (int) ($preds[$uid] ?? 0),
            ])
            ->sortByDesc('total_points')
            ->values()
            ->toArray();
    }

    // ─── Prediction standings ────────────────────────────────────────────
    public function predictionStandings(): array
    {
        if (!$this->tournamentId)
            return [];

        return Prediction::select('predictions.user_id', DB::raw('SUM(predictions.points_earned) as total'))
            ->join('fixtures', 'predictions.fixture_id', '=', 'fixtures.id')
            ->where('fixtures.tournament_id', $this->tournamentId)
            ->whereNotNull('predictions.verified_at')
            ->groupBy('predictions.user_id')
            ->orderByDesc('total')
            ->with('user')
            ->get()
            ->map(fn($row) => [
                'manager' => $row->user->name,
                'faculty' => $row->user->faculty ?? '—',
                'total' => (int) $row->total,
            ])->toArray();
    }

    // ─── Faculty standings: combined fantasy + prediction points per faculty ─
    public function facultyStandings(): array
    {
        if (!$this->tournamentId)
            return [];

        $tid = $this->tournamentId;

        $fantasy = FantasyTeam::join('users', 'fantasy_teams.user_id', '=', 'users.id')
            ->where('fantasy_teams.tournament_id', $tid)
            ->whereNotNull('users.faculty')
            ->groupBy('users.faculty')
            ->selectRaw('users.faculty as faculty, SUM(fantasy_teams.total_points) as pts')
            ->pluck('pts', 'faculty');

        $preds = Prediction::join('users', 'predictions.user_id', '=', 'users.id')
            ->join('fixtures', 'predictions.fixture_id', '=', 'fixtures.id')
            ->where('fixtures.tournament_id', $tid)
            ->whereNotNull('predictions.verified_at')
            ->whereNotNull('users.faculty')
            ->groupBy('users.faculty')
            ->selectRaw('users.faculty as faculty, SUM(predictions.points_earned) as pts')
            ->pluck('pts', 'faculty');

        $faculties = $fantasy->keys()->merge($preds->keys())->unique();

        return $faculties->map(fn($f) => [
                'faculty' => $f,
                'total'   => (int) ($fantasy[$f] ?? 0) + (int) ($preds[$f] ?? 0),
            ])
            ->sortByDesc('total')
            ->values()
            ->toArray();
    }
} ?>

<div class="max-w-5xl mx-auto px-5 sm:px-8 py-10 space-y-6">

    {{-- ── Back to previous page ──────────────────────────────────────────────── --}}
    <button type="button" onclick="window.history.back()"
        class="inline-flex items-center gap-1.5 font-mono text-[11px] text-on-surface-variant/60 hover:text-[#00E676] transition-colors cursor-pointer">
        <span class="material-symbols-outlined text-[16px]">arrow_back</span>
        Back
    </button>

    {{-- Header --}}
    <div class="text-center mb-8">
        <h1 class="font-display font-black text-3xl sm:text-4xl text-white tracking-tight mb-2">
            Leaderboard & <span style="color:#00E676;">Standings</span>
        </h1>
        <div class="inline-flex items-center gap-2 mt-2">
            <span class="w-2 h-2 rounded-full animate-pulse" style="background:#00E676;"></span>
            <span class="font-mono text-[10px] uppercase tracking-widest text-on-surface-variant/60">Live
                standings</span>
        </div>
    </div>
    <livewire:campus-ad-banner />
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
    <div
        class="flex gap-2 justify-center p-1 rounded-xl bg-white/[0.03] border border-outline-variant/15 w-fit mx-auto">
        @foreach(['fantasy' => 'Overall', 'predictions' => 'Predictions', 'faculty' => 'Faculty'] as $val => $label)
            <button wire:click="$set('tab', '{{ $val }}')" class="px-5 py-2 rounded-lg font-mono text-xs font-bold uppercase tracking-wider transition-all
                                       {{ $tab === $val ? 'text-black' : 'text-on-surface-variant hover:text-white' }}"
                style="{{ $tab === $val ? 'background:#00E676;' : '' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- ═══ FANTASY TAB ═══ --}}
    @if($tab === 'fantasy')
        @php $rows = $this->fantasyStandings(); @endphp

        {{-- Matchday filter --}}
        <div class="rounded-2xl border border-outline-variant/15 p-4" style="background: rgba(13,17,15,0.8);">
            <div class="flex gap-2 flex-wrap items-center">
                <button wire:click="$set('selectedMatchday', null)"
                    class="px-4 py-2 rounded-xl font-mono text-xs font-bold uppercase tracking-wider border transition-all cursor-pointer"
                    style="{{ $selectedMatchday === null
            ? 'background:#00E676; color:#000; border-color:#00E676;'
            : 'background:rgba(255,255,255,0.04); color:rgba(255,255,255,0.5); border-color:rgba(255,255,255,0.15);' }}">
                    Overall
                </button>
                @foreach($availableMatchdays as $md)
                    <button wire:click="$set('selectedMatchday', {{ $md }})"
                        class="px-4 py-2 rounded-xl font-mono text-xs font-bold uppercase tracking-wider border transition-all cursor-pointer"
                        style="{{ $selectedMatchday === $md
                    ? 'background:#00E676; color:#000; border-color:#00E676;'
                    : 'background:rgba(255,255,255,0.04); color:rgba(255,255,255,0.5); border-color:rgba(255,255,255,0.15);' }}">
                        MD{{ $md }}
                    </button>
                @endforeach
            </div>
        </div>

        <div class="rounded-2xl border border-outline-variant/15 overflow-hidden" style="background: rgba(13,17,15,0.8);">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[400px]">
                    <thead>
                        <tr class="border-b border-outline-variant/15 font-mono text-xs uppercase tracking-wider text-on-surface-variant/60"
                            style="background: rgba(255,255,255,0.02);">
                            <th class="py-3.5 px-5 w-16">#</th>
                            <th class="py-3.5 px-5">Manager</th>
                            <th class="py-3.5 px-5">Faculty</th>
                            <th class="py-3.5 px-5 text-right">Points</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/10 text-sm">
                        @forelse($rows as $i => $row)
                            <tr class="hover:bg-white/[0.02] transition-colors">
                                <td class="py-4 px-5 font-mono font-bold {{ $i < 3 ? '' : 'text-on-surface-variant/60' }}"
                                    style="{{ $i < 3 ? 'color:#00E676;' : '' }}">{{ $i + 1 }}</td>
                                <td class="py-4 px-5">
                                    <div class="font-bold text-white">{{ $row['manager'] }}</div>
                                    <div class="text-xs text-on-surface-variant/50 font-mono">
                                        Fantasy {{ $row['fantasy_points'] }} · Predictions {{ $row['prediction_points'] }}
                                    </div>
                                </td>
                                <td class="py-4 px-5 text-on-surface-variant/70 font-mono text-xs">{{ $row['faculty'] }}</td>
                                <td class="py-4 px-5 text-right font-mono font-bold" style="color:#00E676;">
                                    {{ $row['total_points'] }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-12 text-center font-mono text-xs text-on-surface-variant/40">No squads
                                    yet
                                    for this tournament.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- ═══ PREDICTIONS TAB ═══ --}}
    @if($tab === 'predictions')
        @php $rows = $this->predictionStandings(); @endphp
        <div class="rounded-2xl border border-outline-variant/15 overflow-hidden" style="background: rgba(13,17,15,0.8);">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[400px]">
                    <thead>
                        <tr class="border-b border-outline-variant/15 font-mono text-xs uppercase tracking-wider text-on-surface-variant/60"
                            style="background: rgba(255,255,255,0.02);">
                            <th class="py-3.5 px-5 w-16">#</th>
                            <th class="py-3.5 px-5">Predictor</th>
                            <th class="py-3.5 px-5">Faculty</th>
                            <th class="py-3.5 px-5 text-right">Points</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/10 text-sm">
                        @forelse($rows as $i => $row)
                            <tr class="hover:bg-white/[0.02] transition-colors">
                                <td class="py-4 px-5 font-mono font-bold {{ $i < 3 ? '' : 'text-on-surface-variant/60' }}"
                                    style="{{ $i < 3 ? 'color:#00E676;' : '' }}">{{ $i + 1 }}</td>
                                <td class="py-4 px-5 font-bold text-white">{{ $row['manager'] }}</td>
                                <td class="py-4 px-5 text-on-surface-variant/70 font-mono text-xs">{{ $row['faculty'] }}</td>
                                <td class="py-4 px-5 text-right font-mono font-bold" style="color:#00E676;">{{ $row['total'] }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-12 text-center font-mono text-xs text-on-surface-variant/40">No
                                    verified
                                    predictions yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- ═══ FACULTY TAB ═══ --}}
    @if($tab === 'faculty')
        @php $rows = $this->facultyStandings(); @endphp
        <div class="rounded-2xl border border-outline-variant/15 overflow-hidden" style="background: rgba(13,17,15,0.8);">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[320px]">
                    <thead>
                        <tr class="border-b border-outline-variant/15 font-mono text-xs uppercase tracking-wider text-on-surface-variant/60"
                            style="background: rgba(255,255,255,0.02);">
                            <th class="py-3.5 px-5 w-16">#</th>
                            <th class="py-3.5 px-5">Faculty</th>
                            <th class="py-3.5 px-5 text-right">Total Points</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/10 text-sm">
                        @forelse($rows as $i => $row)
                            <tr class="hover:bg-white/[0.02] transition-colors">
                                <td class="py-4 px-5 font-mono font-bold {{ $i < 3 ? '' : 'text-on-surface-variant/60' }}"
                                    style="{{ $i < 3 ? 'color:#00E676;' : '' }}">{{ $i + 1 }}</td>
                                <td class="py-4 px-5 font-bold text-white">{{ $row['faculty'] }}</td>
                                <td class="py-4 px-5 text-right font-mono font-bold" style="color:#00E676;">{{ $row['total'] }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="py-12 text-center font-mono text-xs text-on-surface-variant/40">No
                                    faculty
                                    data yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @push('ads')
        @include('partials.propeller-ad')
    @endpush
</div>