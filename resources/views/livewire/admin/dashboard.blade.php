<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Lazy;
use App\Models\User;
use App\Models\Tournament;
use App\Models\Team;
use App\Models\Player;
use App\Models\Fixture;
use App\Enums\FixtureStatus;

new #[Layout('layouts.admin'), Lazy] class extends Component {

    public int   $tournamentsCount = 0;
    public int   $teamsCount       = 0;
    public int   $playersCount     = 0;
    public int   $fixturesCount    = 0;
    public int   $usersCount       = 0;
    public int   $liveCount        = 0;
    public int   $scheduledCount   = 0;
    public int   $completedCount   = 0;
    public array $upcomingFixtures = [];
    public array $recentResults    = [];

    public function placeholder(): string
    {
        return <<<'HTML'
        <div class="p-5 sm:p-8">
            <div class="h-8 shimmer rounded-xl w-48 mb-6"></div>
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div class="h-28 shimmer rounded-2xl"></div>
                <div class="h-28 shimmer rounded-2xl"></div>
                <div class="h-28 shimmer rounded-2xl"></div>
                <div class="h-28 shimmer rounded-2xl"></div>
            </div>
            <div class="h-64 shimmer rounded-2xl mb-4"></div>
            <div class="h-48 shimmer rounded-2xl"></div>
        </div>
        HTML;
    }

    public function mount(): void
    {
        $this->tournamentsCount = Tournament::count();
        $this->teamsCount       = Team::count();
        $this->playersCount     = Player::count();
        $this->fixturesCount    = Fixture::count();
        $this->usersCount       = User::count();

        $this->liveCount      = Fixture::where('status', FixtureStatus::Live)->count();
        $this->scheduledCount = Fixture::where('status', FixtureStatus::Scheduled)->count();
        $this->completedCount = Fixture::where('status', FixtureStatus::Completed)->count();

        $this->upcomingFixtures = Fixture::with(['homeTeam', 'awayTeam'])
            ->where('status', FixtureStatus::Scheduled)
            ->whereNotNull('date')
            ->orderBy('date')
            ->limit(5)
            ->get()
            ->map(fn($f) => [
                'home'     => $f->homeTeam?->name ?? 'TBD',
                'away'     => $f->awayTeam?->name ?? 'TBD',
                'matchday' => $f->matchday ?? 1,
                'date'     => $f->date ? \Carbon\Carbon::parse($f->date)->format('d M Y, H:i') : 'TBC',
            ])
            ->toArray();

        $this->recentResults = Fixture::with(['homeTeam', 'awayTeam'])
            ->where('status', FixtureStatus::Completed)
            ->orderByDesc('date')
            ->limit(5)
            ->get()
            ->map(fn($f) => [
                'home'       => $f->homeTeam?->name ?? 'TBD',
                'away'       => $f->awayTeam?->name ?? 'TBD',
                'home_score' => $f->home_score ?? 0,
                'away_score' => $f->away_score ?? 0,
                'matchday'   => $f->matchday ?? 1,
            ])
            ->toArray();
    }

} ?>

<div class="space-y-8">

    {{-- Hero Banner --}}
    <div class="relative overflow-hidden rounded-2xl p-7 sm:p-10 border border-outline-variant/15 bg-gradient-to-br from-[#121714] to-[#080c0a] glow-green/5">
        <div class="absolute -right-16 -bottom-16 w-64 h-64 rounded-full bg-primary-container/5 filter blur-3xl pointer-events-none"></div>
        <div class="relative z-10 flex flex-col sm:flex-row sm:items-center justify-between gap-6">
            <div>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 mb-4 rounded-full text-[9px] font-mono font-bold uppercase tracking-widest"
                      style="background:rgba(0,230,118,0.1); color:#00E676; border:1px solid rgba(0,230,118,0.2);">
                    <span class="w-1.5 h-1.5 rounded-full animate-pulse" style="background:#00E676;"></span>
                    @if($liveCount > 0) {{ $liveCount }} Match{{ $liveCount > 1 ? 'es' : '' }} Live @else System Active @endif
                </span>
                <h2 class="text-3xl font-display font-black text-on-surface mb-2">Admin Dashboard</h2>
                <p class="text-on-surface-variant text-sm max-w-xl leading-relaxed">
                    Manage tournaments, teams, players, fixtures and results for the PitchIQ fantasy football platform.
                </p>
            </div>

            {{-- Upcoming Fixtures Scroller --}}
            @if(count($upcomingFixtures) > 0)
                <div class="flex-shrink-0 w-full sm:w-64"
                     x-data="{
                         fixtures: {{ Js::from($upcomingFixtures) }},
                         current: 0,
                         visible: true,
                         interval: null,
                         go(i) {
                             if (i === this.current) return;
                             this.visible = false;
                             setTimeout(() => { this.current = i; this.visible = true; }, 180);
                         },
                         start() {
                             if (this.fixtures.length > 1) {
                                 this.interval = setInterval(() => {
                                     this.go((this.current + 1) % this.fixtures.length);
                                 }, 3500);
                             }
                         },
                         stop() { clearInterval(this.interval); }
                     }"
                     x-init="start()"
                     @mouseenter="stop()"
                     @mouseleave="start()">

                    <div class="rounded-xl border border-outline-variant/15 bg-surface-container/20 px-5 py-4">
                        <div class="flex items-center justify-between mb-3">
                            <p class="text-[9px] font-mono uppercase tracking-widest text-on-surface-variant/50">Upcoming Fixtures</p>
                            <template x-if="fixtures.length > 1">
                                <div class="flex items-center gap-1">
                                    <template x-for="(f, i) in fixtures" :key="i">
                                        <button @click="stop(); go(i); start()"
                                                class="w-1.5 h-1.5 rounded-full transition-all duration-300 cursor-pointer"
                                                :class="current === i ? 'bg-primary-container scale-125' : 'bg-outline-variant/30 hover:bg-outline-variant/60'">
                                        </button>
                                    </template>
                                </div>
                            </template>
                        </div>

                        {{-- Single fixture display — opacity/transform only, preserves layout height so no jump --}}
                        <div :class="visible ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-1'"
                             class="space-y-2 transition-all duration-200 ease-out">

                            {{-- Home team --}}
                            <div class="flex items-center gap-2.5">
                                <span class="w-2 h-2 rounded-full flex-shrink-0" style="background:#00E676; box-shadow:0 0 5px rgba(0,230,118,0.5);"></span>
                                <p class="font-display font-black text-on-surface text-sm leading-tight" x-text="fixtures[current].home"></p>
                            </div>

                            {{-- vs divider --}}
                            <div class="flex items-center gap-2 pl-1">
                                <span class="h-px flex-1 bg-outline-variant/15"></span>
                                <span class="font-mono text-[9px] font-bold text-on-surface-variant/30 px-1.5">vs</span>
                                <span class="h-px flex-1 bg-outline-variant/15"></span>
                            </div>

                            {{-- Away team --}}
                            <div class="flex items-center gap-2.5">
                                <span class="w-2 h-2 rounded-full flex-shrink-0 bg-on-surface-variant/30"></span>
                                <p class="font-display font-black text-on-surface text-sm leading-tight" x-text="fixtures[current].away"></p>
                            </div>

                            {{-- Meta row --}}
                            <div class="flex items-center justify-between pt-1 border-t border-outline-variant/10">
                                <span class="font-mono text-[9px] font-bold text-on-surface-variant bg-surface-container/30 px-2 py-0.5 rounded border border-outline-variant/20"
                                      x-text="'MD' + fixtures[current].matchday"></span>
                                <span class="font-mono text-[9px] text-on-surface-variant/40" x-text="fixtures[current].date"></span>
                            </div>
                        </div>

                        <template x-if="fixtures.length > 1">
                            <p class="mt-3 text-[9px] font-mono text-on-surface-variant/25 text-right"
                               x-text="(current + 1) + ' / ' + fixtures.length"></p>
                        </template>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Stat Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-5">
        <a href="{{ route('admin.tournaments') }}" class="group neo-surface p-6 rounded-2xl flex items-center justify-between hover-lift">
            <div>
                <p class="text-on-surface-variant text-[10px] font-mono uppercase tracking-widest mb-1">Tournaments</p>
                <p class="text-3xl font-display font-black text-on-surface group-hover:text-primary-container transition-colors">{{ $tournamentsCount }}</p>
            </div>
            <div class="w-12 h-12 rounded-xl flex items-center justify-center bg-primary-container/10 border border-primary-container/20 group-hover:border-primary-container/40 group-hover:bg-primary-container/20 transition-all duration-300">
                <span class="material-symbols-outlined text-[22px] text-primary-container">emoji_events</span>
            </div>
        </a>
        <a href="{{ route('admin.teams') }}" class="group neo-surface p-6 rounded-2xl flex items-center justify-between hover-lift">
            <div>
                <p class="text-on-surface-variant text-[10px] font-mono uppercase tracking-widest mb-1">Teams</p>
                <p class="text-3xl font-display font-black text-on-surface group-hover:text-primary-container transition-colors">{{ $teamsCount }}</p>
            </div>
            <div class="w-12 h-12 rounded-xl flex items-center justify-center bg-primary-container/10 border border-primary-container/20 group-hover:border-primary-container/40 group-hover:bg-primary-container/20 transition-all duration-300">
                <span class="material-symbols-outlined text-[22px] text-primary-container">groups</span>
            </div>
        </a>
        <a href="{{ route('admin.players') }}" class="group neo-surface p-6 rounded-2xl flex items-center justify-between hover-lift">
            <div>
                <p class="text-on-surface-variant text-[10px] font-mono uppercase tracking-widest mb-1">Players</p>
                <p class="text-3xl font-display font-black text-on-surface group-hover:text-primary-container transition-colors">{{ $playersCount }}</p>
            </div>
            <div class="w-12 h-12 rounded-xl flex items-center justify-center bg-primary-container/10 border border-primary-container/20 group-hover:border-primary-container/40 group-hover:bg-primary-container/20 transition-all duration-300">
                <span class="material-symbols-outlined text-[22px] text-primary-container">sports_soccer</span>
            </div>
        </a>
        <a href="{{ route('admin.fixtures') }}" class="group neo-surface p-6 rounded-2xl flex items-center justify-between hover-lift">
            <div>
                <p class="text-on-surface-variant text-[10px] font-mono uppercase tracking-widest mb-1">Fixtures</p>
                <p class="text-3xl font-display font-black text-on-surface group-hover:text-primary-container transition-colors">{{ $fixturesCount }}</p>
            </div>
            <div class="w-12 h-12 rounded-xl flex items-center justify-center bg-primary-container/10 border border-primary-container/20 group-hover:border-primary-container/40 group-hover:bg-primary-container/20 transition-all duration-300">
                <span class="material-symbols-outlined text-[22px] text-primary-container">calendar_month</span>
            </div>
        </a>
        <div class="group neo-surface p-6 rounded-2xl flex items-center justify-between">
            <div>
                <p class="text-on-surface-variant text-[10px] font-mono uppercase tracking-widest mb-1">Registered Users</p>
                <p class="text-3xl font-display font-black text-on-surface">{{ $usersCount }}</p>
            </div>
            <div class="w-12 h-12 rounded-xl flex items-center justify-center bg-primary-container/10 border border-primary-container/20">
                <span class="material-symbols-outlined text-[22px] text-primary-container">person</span>
            </div>
        </div>
    </div>

    {{-- Fixture Status Strip --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="neo-surface rounded-xl px-5 py-4 flex items-center gap-4 border border-error/10">
            <span class="w-2.5 h-2.5 rounded-full bg-error animate-pulse flex-shrink-0"></span>
            <div>
                <p class="text-[9px] font-mono uppercase tracking-widest text-on-surface-variant/50">Live</p>
                <p class="text-xl font-display font-black text-error">{{ $liveCount }}</p>
            </div>
        </div>
        <div class="neo-surface rounded-xl px-5 py-4 flex items-center gap-4 border border-primary-container/10">
            <span class="w-2.5 h-2.5 rounded-full bg-primary-container flex-shrink-0"></span>
            <div>
                <p class="text-[9px] font-mono uppercase tracking-widest text-on-surface-variant/50">Scheduled</p>
                <p class="text-xl font-display font-black text-primary-container">{{ $scheduledCount }}</p>
            </div>
        </div>
        <div class="neo-surface rounded-xl px-5 py-4 flex items-center gap-4 border border-outline-variant/10">
            <span class="w-2.5 h-2.5 rounded-full bg-on-surface-variant/40 flex-shrink-0"></span>
            <div>
                <p class="text-[9px] font-mono uppercase tracking-widest text-on-surface-variant/50">Completed</p>
                <p class="text-xl font-display font-black text-on-surface-variant">{{ $completedCount }}</p>
            </div>
        </div>
    </div>

    {{-- Quick Actions + Recent Results + Status --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Quick Actions --}}
        <div class="neo-surface p-6 sm:p-8 rounded-2xl glow-green/5">
            <h3 class="font-display font-bold text-lg text-on-surface mb-1">Quick Actions</h3>
            <p class="text-on-surface-variant/60 text-xs mb-6">Jump straight into managing key resources.</p>
            <div class="space-y-3">
                @foreach([
                    ['route' => 'admin.tournaments', 'icon' => 'emoji_events',   'label' => 'Manage Tournaments', 'desc' => 'View & edit leagues/cups'],
                    ['route' => 'admin.teams',       'icon' => 'groups',         'label' => 'Manage Teams',       'desc' => 'View & edit dept. teams'],
                    ['route' => 'admin.players',     'icon' => 'sports_soccer',  'label' => 'Manage Players',     'desc' => 'View & edit player catalog'],
                    ['route' => 'admin.fixtures',    'icon' => 'calendar_month', 'label' => 'Manage Fixtures',    'desc' => 'Schedule match fixtures'],
                ] as $action)
                    <a href="{{ route($action['route']) }}"
                       class="group flex items-center gap-4 p-3.5 rounded-xl border border-outline-variant/10 bg-[#080c0a] hover:border-primary-container/40 hover-lift">
                        <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0 bg-primary-container/5 border border-outline-variant/15 group-hover:bg-primary-container/10 group-hover:border-primary-container/30 transition-all duration-300">
                            <span class="material-symbols-outlined text-[17px] text-on-surface-variant group-hover:text-primary-container transition-colors">{{ $action['icon'] }}</span>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-on-surface group-hover:text-primary-container transition-colors">{{ $action['label'] }}</p>
                            <p class="text-[9px] text-on-surface-variant/50 font-mono tracking-wide mt-0.5">{{ $action['desc'] }}</p>
                        </div>
                        <span class="material-symbols-outlined text-[14px] text-on-surface-variant/20 group-hover:text-primary-container/40 ml-auto transition-colors">chevron_right</span>
                    </a>
                @endforeach
            </div>
        </div>

        {{-- Recent Results --}}
        <div class="neo-surface p-6 rounded-2xl glow-green/5">
            <h3 class="font-display font-bold text-lg text-on-surface mb-1">Recent Results</h3>
            <p class="text-on-surface-variant/60 text-xs mb-5">Last completed fixtures.</p>
            @if(count($recentResults) > 0)
                <ul class="space-y-3">
                    @foreach($recentResults as $result)
                        <li class="flex items-center justify-between gap-2 pb-3 border-b border-outline-variant/8 last:border-0 last:pb-0">
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-bold text-on-surface truncate">{{ $result['home'] }}</p>
                                <p class="text-[9px] font-mono text-on-surface-variant/40 mt-0.5">MD{{ $result['matchday'] }}</p>
                            </div>
                            <div class="flex items-center gap-1.5 flex-shrink-0">
                                <span class="font-display font-black text-base text-on-surface tabular-nums">{{ $result['home_score'] }}</span>
                                <span class="text-[9px] font-mono text-on-surface-variant/30">–</span>
                                <span class="font-display font-black text-base text-on-surface tabular-nums">{{ $result['away_score'] }}</span>
                            </div>
                            <div class="flex-1 min-w-0 text-right">
                                <p class="text-xs font-bold text-on-surface truncate">{{ $result['away'] }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @else
                <div class="flex flex-col items-center justify-center py-10 text-center">
                    <span class="material-symbols-outlined text-[32px] text-on-surface-variant/20 mb-2">sports_score</span>
                    <p class="text-on-surface-variant/40 text-xs font-mono">No results yet.</p>
                </div>
            @endif
        </div>

        {{-- System Status --}}
        <div class="neo-surface p-6 rounded-2xl glow-green/5">
            <h3 class="font-display font-bold text-lg text-on-surface mb-5">System Status</h3>
            <ul class="space-y-4">
                @foreach([
                    ['label' => 'Database',      'value' => 'MySQL Active', 'ok' => true],
                    ['label' => 'Livewire Volt', 'value' => 'v4 Active',    'ok' => true],
                    ['label' => 'App Mode',      'value' => 'Development',  'ok' => false],
                    ['label' => 'Auth Guard',    'value' => 'Enabled',      'ok' => true],
                ] as $s)
                    <li class="flex items-center justify-between text-xs pb-3 border-b border-outline-variant/5 last:border-b-0 last:pb-0">
                        <span class="text-on-surface-variant flex items-center gap-2">
                            <span class="w-1.5 h-1.5 rounded-full" style="background:{{ $s['ok'] ? '#00E676' : '#fdd400' }}; box-shadow: 0 0 6px {{ $s['ok'] ? '#00e676' : '#fdd400' }};"></span>
                            {{ $s['label'] }}
                        </span>
                        <span class="font-mono font-bold" style="color:{{ $s['ok'] ? '#00E676' : '#fdd400' }};">{{ $s['value'] }}</span>
                    </li>
                @endforeach
            </ul>
            <div class="mt-6 pt-5 border-t border-outline-variant/10 space-y-2.5">
                <p class="text-[9px] font-mono uppercase tracking-widest text-on-surface-variant/40 mb-3">Record Counts</p>
                @foreach([
                    ['label' => 'Tournaments', 'count' => $tournamentsCount],
                    ['label' => 'Teams',       'count' => $teamsCount],
                    ['label' => 'Players',     'count' => $playersCount],
                    ['label' => 'Fixtures',    'count' => $fixturesCount],
                    ['label' => 'Users',       'count' => $usersCount],
                ] as $item)
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-mono text-on-surface-variant/50">{{ $item['label'] }}</span>
                        <span class="text-[10px] font-mono font-bold text-primary-container">{{ $item['count'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

</div>