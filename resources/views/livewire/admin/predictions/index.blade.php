<?php

use App\Models\FantasyTeam;
use App\Models\Fixture;
use App\Models\Prediction;
use App\Models\User;
use App\Enums\PredictionType;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.admin')] class extends Component {

    public array  $fixtures          = [];
    public ?int   $selectedFixtureId = null;
    public array  $selectedFixture   = [];
    public array  $submissions       = [];
    public string $predictionsTab    = 'pending';
    public string $search            = '';

    public function mount(): void
    {
        $this->loadFixtures();
    }

    public function updatedPredictionsTab(): void
    {
        $this->selectedFixtureId = null;
        $this->selectedFixture   = [];
        $this->submissions       = [];
        $this->loadFixtures();
    }

    public function updatedSearch(): void
    {
        $this->loadFixtures();
    }

    private function loadFixtures(): void
    {
        $this->fixtures = Fixture::with(['homeTeam', 'awayTeam', 'tournament'])
            ->when($this->predictionsTab === 'pending',
                fn($q) => $q->whereIn('status', ['scheduled', 'live', 'postponed'])
            )
            ->when($this->predictionsTab === 'completed',
                fn($q) => $q->where('status', 'completed')
            )
            ->when($this->search, fn($q) =>
                $q->where(fn($sub) =>
                    $sub->whereHas('homeTeam', fn($q) => $q->where('name', 'like', "%{$this->search}%"))
                        ->orWhereHas('awayTeam', fn($q) => $q->where('name', 'like', "%{$this->search}%"))
                )
            )
            ->orderByRaw("CASE WHEN status = 'live' THEN 0 WHEN status = 'scheduled' THEN 1 ELSE 2 END")
            ->orderByDesc('date')
            ->get()
            ->map(function ($f) {
                $predictorCount = Prediction::where('fixture_id', $f->id)
                    ->distinct('user_id')
                    ->count('user_id');
                $squadCount = FantasyTeam::where('fixture_id', $f->id)->count();
                return [
                    'id'               => $f->id,
                    'matchday'         => $f->matchday,
                    'date_label'       => $f->date?->format('d M · H:i'),
                    'status'           => $f->status->value,
                    'status_label'     => $f->status->label(),
                    'status_badge'     => $f->status->badgeClass(),
                    'home_team_name'   => $f->homeTeam?->name ?? 'TBD',
                    'away_team_name'   => $f->awayTeam?->name ?? 'TBD',
                    'home_team_colour' => $f->homeTeam?->colour ?? '#00E676',
                    'away_team_colour' => $f->awayTeam?->colour ?? '#3B82F6',
                    'home_score'       => $f->home_score,
                    'away_score'       => $f->away_score,
                    'tournament_name'  => $f->tournament?->name ?? '',
                    'predictor_count'  => $predictorCount,
                    'squad_count'      => $squadCount,
                ];
            })
            ->toArray();
    }

    public function selectFixture(int $fixtureId): void
    {
        $this->selectedFixtureId = $fixtureId;
        $this->selectedFixture   = collect($this->fixtures)->firstWhere('id', $fixtureId) ?? [];
        $this->loadSubmissions($fixtureId);
    }

    public function clearSelection(): void
    {
        $this->selectedFixtureId = null;
        $this->selectedFixture   = [];
        $this->submissions       = [];
    }

    private function loadSubmissions(int $fixtureId): void
    {
        $userIds = Prediction::where('fixture_id', $fixtureId)
            ->distinct('user_id')
            ->pluck('user_id');

        // Also include users who have squads but no predictions
        $squadUserIds = FantasyTeam::where('fixture_id', $fixtureId)->pluck('user_id');
        $allUserIds   = $userIds->merge($squadUserIds)->unique()->values();

        $this->submissions = User::whereIn('id', $allUserIds)
            ->get()
            ->map(function ($user) use ($fixtureId) {
                $predictions = Prediction::with(['predictedScorer', 'predictedTeam'])
                    ->where('user_id', $user->id)
                    ->where('fixture_id', $fixtureId)
                    ->get()
                    ->map(fn($p) => [
                        'type_label'    => $p->type->label(),
                        'type_icon'     => $p->type->icon(),
                        'value'         => $this->predDisplayValue($p),
                        'points_earned' => $p->points_earned,
                        'is_verified'   => $p->verified_at !== null,
                    ])
                    ->toArray();

                $fantasyTeam = FantasyTeam::with(['fantasyPicks.player.team'])
                    ->where('user_id', $user->id)
                    ->where('fixture_id', $fixtureId)
                    ->first();

                $squad = null;
                if ($fantasyTeam) {
                    $picks = $fantasyTeam->fantasyPicks->map(fn($pick) => [
                        'player_name'     => $pick->player?->name ?? '?',
                        'position'        => $pick->player?->position?->value ?? 'FWD',
                        'team_colour'     => $pick->player?->team?->colour ?? '#00E676',
                        'is_captain'      => $pick->is_captain,
                        'is_vice_captain' => $pick->is_vice_captain,
                        'points_scored'   => $pick->points_scored,
                    ])->toArray();

                    $squad = [
                        'team_name'    => $fantasyTeam->team_name,
                        'formation'    => $fantasyTeam->formation ?? $this->inferFormation($picks),
                        'total_points' => $fantasyTeam->total_points,
                        'picks'        => $picks,
                    ];
                }

                return [
                    'user_id'          => $user->id,
                    'user_name'        => $user->name,
                    'faculty'          => $user->faculty ?? '',
                    'pred_count'       => count($predictions),
                    'pred_points'      => collect($predictions)->sum('points_earned'),
                    'squad_points'     => $squad ? $squad['total_points'] : 0,
                    'predictions'      => $predictions,
                    'squad'            => $squad,
                ];
            })
            ->sortByDesc('pred_points')
            ->values()
            ->toArray();
    }

    private function predDisplayValue(Prediction $p): string
    {
        return match($p->type) {
            PredictionType::Result          => $p->predicted_result?->label() ?? '—',
            PredictionType::ExactScore      => ($p->predicted_home_score ?? '?') . ' – ' . ($p->predicted_away_score ?? '?'),
            PredictionType::FirstGoalscorer => $p->predictedScorer?->name ?? '—',
            PredictionType::CleanSheet      => $p->predictedTeam?->name  ?? '—',
            PredictionType::CardedPlayer    => $p->predictedScorer?->name ?? '—',
        };
    }

    private function inferFormation(array $picks): string
    {
        $c = collect($picks);
        return $c->where('position', 'DEF')->count()
            . '-' . $c->where('position', 'MID')->count()
            . '-' . $c->where('position', 'FWD')->count();
    }
} ?>

<div class="space-y-6">

    {{-- ── Header ─────────────────────────────────────────────────────────── --}}
    <div class="flex items-center justify-between gap-4">
        <div>
            <h2 class="font-display font-black text-xl text-white uppercase tracking-tight">User Predictions</h2>
            <p class="font-mono text-xs text-on-surface-variant/50 mt-0.5">Browse submitted predictions and squads per fixture</p>
        </div>
        <span class="font-mono text-[10px] text-on-surface-variant/40">{{ count($fixtures) }} fixture(s)</span>
    </div>

    {{-- ── Tabs ───────────────────────────────────────────────────────────── --}}
    <div class="flex gap-1 p-1 rounded-xl border border-outline-variant/15" style="background:rgba(255,255,255,0.02);">
        <button wire:click="$set('predictionsTab', 'pending')"
                class="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg font-mono text-xs font-bold uppercase tracking-wider transition-all duration-150 cursor-pointer"
                style="{{ $predictionsTab === 'pending'
                    ? 'background:rgba(0,230,118,0.12); color:#00E676; border:1px solid rgba(0,230,118,0.25);'
                    : 'color:rgba(255,255,255,0.4); border:1px solid transparent;' }}">
            <span class="material-symbols-outlined text-[14px]">pending</span>
            Pending
        </button>
        <button wire:click="$set('predictionsTab', 'completed')"
                class="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg font-mono text-xs font-bold uppercase tracking-wider transition-all duration-150 cursor-pointer"
                style="{{ $predictionsTab === 'completed'
                    ? 'background:rgba(0,230,118,0.12); color:#00E676; border:1px solid rgba(0,230,118,0.25);'
                    : 'color:rgba(255,255,255,0.4); border:1px solid transparent;' }}">
            <span class="material-symbols-outlined text-[14px]">check_circle</span>
            Completed
        </button>
    </div>

    @if(!$selectedFixtureId)

        {{-- ── Search ──────────────────────────────────────────────────────── --}}
        <div class="relative">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant/40 text-[16px]">search</span>
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search by team name..."
                   class="w-full pl-9 pr-4 py-2.5 rounded-xl border border-outline-variant/20 text-sm text-on-surface placeholder:text-on-surface-variant/30 focus:outline-none focus:border-[#00E676]/40 transition-all font-mono"
                   style="background:rgba(255,255,255,0.05);" />
        </div>

        {{-- ── Fixture grid ─────────────────────────────────────────────────── --}}
        @forelse($fixtures as $fix)
            <div wire:key="fix-{{ $fix['id'] }}"
                 class="rounded-xl border transition-all cursor-pointer hover:border-primary-container/40"
                 style="background:rgba(13,17,15,0.8);"
                 wire:click="selectFixture({{ $fix['id'] }})"
                 :class="'{{ $fix['predictor_count'] > 0 ? 'border-outline-variant/20' : 'border-outline-variant/10 opacity-60' }}'">
                <div class="p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    {{-- Teams & meta --}}
                    <div class="flex items-center gap-4">
                        <div class="flex-shrink-0 w-11 h-11 rounded-xl border border-outline-variant/20 flex flex-col items-center justify-center"
                             style="background:rgba(0,230,118,0.06);">
                            <span class="font-mono text-[7px] text-on-surface-variant/50 uppercase tracking-widest">MD</span>
                            <span class="font-mono font-black text-base" style="color:#00E676;">{{ $fix['matchday'] }}</span>
                        </div>
                        <div>
                            <div class="flex items-center gap-2 font-display font-black text-sm text-white">
                                <span style="color:{{ $fix['home_team_colour'] }}">{{ $fix['home_team_name'] }}</span>
                                @if($fix['status'] !== 'scheduled')
                                    <span class="font-mono font-black text-white">{{ $fix['home_score'] ?? 0 }}–{{ $fix['away_score'] ?? 0 }}</span>
                                @else
                                    <span class="text-on-surface-variant/30 font-sans font-normal text-xs">vs</span>
                                @endif
                                <span style="color:{{ $fix['away_team_colour'] }}">{{ $fix['away_team_name'] }}</span>
                            </div>
                            <p class="font-mono text-[10px] text-on-surface-variant/40 mt-0.5">
                                {{ $fix['tournament_name'] }}@if($fix['date_label']) · {{ $fix['date_label'] }}@endif
                            </p>
                        </div>
                    </div>
                    {{-- Stats + status --}}
                    <div class="flex items-center gap-4 flex-shrink-0">
                        <div class="text-center">
                            <span class="block font-black text-xl font-mono" style="color:#00E676;">{{ $fix['predictor_count'] }}</span>
                            <span class="font-mono text-[9px] text-on-surface-variant/50 uppercase">predictors</span>
                        </div>
                        <div class="text-center">
                            <span class="block font-black text-xl font-mono text-white">{{ $fix['squad_count'] }}</span>
                            <span class="font-mono text-[9px] text-on-surface-variant/50 uppercase">squads</span>
                        </div>
                        <span class="px-2.5 py-1 rounded-full font-mono text-[10px] font-bold uppercase tracking-wider {{ $fix['status_badge'] }}">
                            {{ $fix['status_label'] }}
                        </span>
                        <span class="material-symbols-outlined text-[18px] text-on-surface-variant/30">chevron_right</span>
                    </div>
                </div>
            </div>
        @empty
            <div class="rounded-xl border border-outline-variant/15 p-12 text-center" style="background:rgba(13,17,15,0.8);">
                <span class="material-symbols-outlined text-4xl text-on-surface-variant/20 block mb-3">calendar_month</span>
                <p class="font-mono text-xs text-on-surface-variant/40">
                    {{ $predictionsTab === 'completed' ? 'No completed fixtures yet.' : 'No pending fixtures.' }}
                </p>
            </div>
        @endforelse

    @else

        {{-- ── Drill-down: submissions for selected fixture ─────────────────── --}}
        <div class="flex items-center gap-3 mb-2">
            <button wire:click="clearSelection"
                    class="inline-flex items-center gap-1.5 font-mono text-[10px] text-on-surface-variant/50 hover:text-primary-container transition-colors cursor-pointer">
                <span class="material-symbols-outlined text-[14px]">arrow_back</span>
                All fixtures
            </button>
            <span class="text-on-surface-variant/20">·</span>
            <span class="font-mono text-[10px] text-on-surface-variant/50">
                <strong class="text-white" style="font-family:inherit;">{{ $selectedFixture['home_team_name'] ?? '' }} vs {{ $selectedFixture['away_team_name'] ?? '' }}</strong>
                · {{ count($submissions) }} submission(s)
            </span>
        </div>

        {{-- Selected fixture info strip --}}
        <div class="rounded-xl border border-outline-variant/20 p-4 flex items-center justify-between gap-4"
             style="background:rgba(13,17,15,0.8);">
            <div class="flex items-center gap-3">
                <div class="flex items-center gap-2 font-display font-black text-base">
                    <span style="color:{{ $selectedFixture['home_team_colour'] ?? '#00E676' }}">{{ $selectedFixture['home_team_name'] ?? '' }}</span>
                    @if(($selectedFixture['status'] ?? '') !== 'scheduled')
                        <span class="font-mono font-black text-white">{{ $selectedFixture['home_score'] ?? 0 }}–{{ $selectedFixture['away_score'] ?? 0 }}</span>
                    @else
                        <span class="text-on-surface-variant/30 font-sans font-normal text-xs">vs</span>
                    @endif
                    <span style="color:{{ $selectedFixture['away_team_colour'] ?? '#3B82F6' }}">{{ $selectedFixture['away_team_name'] ?? '' }}</span>
                </div>
                <span class="px-2.5 py-1 rounded-full font-mono text-[10px] font-bold uppercase tracking-wider {{ $selectedFixture['status_badge'] ?? '' }}">
                    {{ $selectedFixture['status_label'] ?? '' }}
                </span>
            </div>
            <span class="font-mono text-[10px] text-on-surface-variant/40">MD{{ $selectedFixture['matchday'] ?? '' }}@if($selectedFixture['date_label'] ?? null) · {{ $selectedFixture['date_label'] }}@endif</span>
        </div>

        @if(empty($submissions))
            <div class="rounded-xl border border-outline-variant/15 p-12 text-center" style="background:rgba(13,17,15,0.8);">
                <span class="material-symbols-outlined text-4xl text-on-surface-variant/20 block mb-3">person_off</span>
                <p class="font-mono text-xs text-on-surface-variant/40">No submissions for this fixture yet.</p>
            </div>
        @else

            @foreach($submissions as $i => $sub)
                <div wire:key="sub-{{ $sub['user_id'] }}"
                     x-data="{ open: {{ $i === 0 ? 'true' : 'false' }} }"
                     class="rounded-xl border border-outline-variant/15 overflow-hidden"
                     style="background:rgba(13,17,15,0.8);">

                    {{-- User row header --}}
                    <button @click="open = !open"
                            class="w-full flex items-center justify-between px-5 py-4 hover:bg-white/[0.02] transition-colors cursor-pointer">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full font-black text-sm text-background flex items-center justify-center flex-shrink-0"
                                 style="background: linear-gradient(135deg, #00E676 0%, #00b359 100%);">
                                {{ strtoupper(substr($sub['user_name'], 0, 1)) }}
                            </div>
                            <div class="text-left">
                                <span class="block font-bold text-sm text-white">{{ $sub['user_name'] }}</span>
                                @if($sub['faculty'])
                                    <span class="font-mono text-[10px] text-on-surface-variant/50">{{ $sub['faculty'] }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="flex items-center gap-2 sm:gap-4">
                            @if($sub['pred_count'] > 0)
                                <span class="hidden sm:inline font-mono text-[10px] text-on-surface-variant/50">
                                    {{ $sub['pred_count'] }} pred(s)
                                </span>
                            @endif
                            @if($sub['pred_points'] > 0)
                                <span class="font-mono text-xs font-black px-2.5 py-1 rounded-full"
                                      style="background:rgba(0,230,118,0.12); color:#00E676; border:1px solid rgba(0,230,118,0.25);">
                                    +{{ $sub['pred_points'] }} pts
                                </span>
                            @endif
                            @if($sub['squad'])
                                <span class="hidden sm:inline-flex font-mono text-[10px] text-on-surface-variant/40 items-center gap-1">
                                    <span class="material-symbols-outlined text-[12px]">groups</span>
                                    {{ $sub['squad']['formation'] }}
                                </span>
                            @endif
                            <span class="material-symbols-outlined text-[18px] text-on-surface-variant/40 transition-transform"
                                  :class="open ? 'rotate-180' : ''">expand_more</span>
                        </div>
                    </button>

                    {{-- Expandable body --}}
                    <div x-show="open" x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 -translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         class="border-t border-outline-variant/10">

                        <div class="grid grid-cols-1 lg:grid-cols-2 divide-y lg:divide-y-0 lg:divide-x divide-outline-variant/10">

                            {{-- Predictions list --}}
                            <div class="p-5 space-y-1.5">
                                <h5 class="font-display font-black text-xs uppercase tracking-wider text-white mb-3">Predictions</h5>
                                @forelse($sub['predictions'] as $pred)
                                    <div class="flex items-center gap-3 px-3 py-2.5 rounded-xl border border-outline-variant/15 bg-white/[0.02]">
                                        <span class="material-symbols-outlined text-[15px] text-on-surface-variant/50 flex-shrink-0">{{ $pred['type_icon'] }}</span>
                                        <div class="flex-1 min-w-0">
                                            <span class="block font-mono text-[9px] text-on-surface-variant/40 uppercase tracking-wider">{{ $pred['type_label'] }}</span>
                                            <span class="block font-bold text-sm text-white truncate">{{ $pred['value'] }}</span>
                                        </div>
                                        @if($pred['is_verified'])
                                            <span class="font-mono text-xs font-black px-2 py-0.5 rounded-lg flex-shrink-0
                                                         {{ $pred['points_earned'] > 0 ? 'text-[#00E676]' : 'text-on-surface-variant/40' }}"
                                                  style="{{ $pred['points_earned'] > 0 ? 'background:rgba(0,230,118,0.12);' : '' }}">
                                                {{ $pred['points_earned'] > 0 ? '+' . $pred['points_earned'] : '✗ 0' }}
                                            </span>
                                        @else
                                            <span class="font-mono text-[10px] text-on-surface-variant/30 italic flex-shrink-0">pending</span>
                                        @endif
                                    </div>
                                @empty
                                    <p class="font-mono text-xs text-on-surface-variant/40 py-4 text-center">No predictions submitted.</p>
                                @endforelse
                            </div>

                            {{-- Formation pitch --}}
                            <div class="p-5">
                                @if($sub['squad'])
                                    @php
                                        $sq        = $sub['squad'];
                                        $fParts    = explode('-', $sq['formation']);
                                        $pitchRows = [
                                            'GK'  => 1,
                                            'DEF' => (int)($fParts[0] ?? 4),
                                            'MID' => (int)($fParts[1] ?? 3),
                                            'FWD' => (int)($fParts[2] ?? 3),
                                        ];
                                        $picksByPos = collect($sq['picks'])->groupBy('position');
                                    @endphp

                                    <div class="flex items-center justify-between mb-3">
                                        <h5 class="font-display font-black text-xs uppercase tracking-wider text-white">{{ $sq['team_name'] }}</h5>
                                        <div class="flex items-center gap-2">
                                            <span class="font-mono text-[10px] text-on-surface-variant/50">{{ $sq['formation'] }}</span>
                                            @if($sq['total_points'] > 0)
                                                <span class="font-mono text-xs font-black px-2 py-0.5 rounded-lg"
                                                      style="background:rgba(0,230,118,0.12); color:#00E676;">
                                                    {{ $sq['total_points'] }} pts
                                                </span>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="rounded-xl border border-[#00E676]/10 p-3 flex flex-col justify-between gap-3"
                                         style="min-height:280px; background: repeating-linear-gradient(0deg, rgba(0,230,118,0.02) 0px, rgba(0,230,118,0.02) 32px, transparent 32px, transparent 64px);">
                                        @foreach($pitchRows as $pos => $slotCount)
                                            @php $rowPlayers = $picksByPos[$pos] ?? collect(); @endphp
                                            @if($rowPlayers->isNotEmpty())
                                                <div class="flex justify-around items-end gap-1">
                                                    @foreach($rowPlayers as $pl)
                                                        <div class="text-center flex flex-col items-center" style="max-width:68px;">
                                                            <div class="relative w-10 h-10 rounded-full flex items-center justify-center font-black text-[9px] border-2"
                                                                 style="background:{{ $pl['team_colour'] }}22; border-color:{{ $pl['team_colour'] }}; color:{{ $pl['team_colour'] }};">
                                                                {{ $pos }}
                                                                @if($pl['is_captain'])
                                                                    <span class="absolute -top-1 -right-1 w-3.5 h-3.5 rounded-full font-black text-[7px] flex items-center justify-center text-black" style="background:#00E676;">C</span>
                                                                @elseif($pl['is_vice_captain'])
                                                                    <span class="absolute -top-1 -right-1 w-3.5 h-3.5 rounded-full font-black text-[7px] flex items-center justify-center text-white border border-white/30" style="background:rgba(255,255,255,0.15);">V</span>
                                                                @endif
                                                            </div>
                                                            <span class="block text-[8px] font-bold text-white mt-0.5 leading-tight text-center" style="max-width:64px; overflow:hidden; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical;">{{ $pl['player_name'] }}</span>
                                                            <span class="block font-mono text-[8px] font-black mt-0.5"
                                                                  style="color:{{ $pl['points_scored'] > 0 ? '#00E676' : 'rgba(255,255,255,0.25)' }}">
                                                                {{ $pl['points_scored'] > 0 ? '+' . $pl['points_scored'] : '0' }}
                                                            </span>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                @else
                                    <div class="flex flex-col items-center justify-center h-full min-h-[200px] text-center">
                                        <span class="material-symbols-outlined text-3xl text-on-surface-variant/20 block mb-2">group_off</span>
                                        <p class="font-mono text-xs text-on-surface-variant/40">No squad submitted.</p>
                                    </div>
                                @endif
                            </div>

                        </div>
                    </div>
                </div>
            @endforeach

        @endif

    @endif

</div>
