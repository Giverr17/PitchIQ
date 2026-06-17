@extends('layouts.app')
@section('title', 'Games')
@section('meta_description', 'Browse live, upcoming and completed campus fantasy football games on PitchIQ. Set your lineup and score points.')

@section('content')
<div class="max-w-7xl mx-auto px-5 sm:px-8 py-12">

    {{-- Page Header --}}
    <div class="mb-10 anim-on-scroll">
        <p class="font-mono text-xs text-primary-container tracking-widest uppercase mb-2">Fantasy Football</p>
        <h1 class="font-display font-black text-5xl text-on-surface tracking-tight mb-3">Games & Fixtures</h1>
        <p class="text-on-surface-variant max-w-xl">Set your squad lineup, track live scores, and earn fantasy points from every campus match.</p>
    </div>

    {{-- Filter Tabs --}}
    <div class="flex items-center gap-2 flex-wrap mb-8 anim-on-scroll" data-filter-group="games">
        @foreach(['all' => 'All Games', 'live' => 'Live Now', 'upcoming' => 'Upcoming', 'completed' => 'Completed'] as $val => $label)
        <button data-filter="{{ $val }}"
                class="font-mono text-xs tracking-wider uppercase px-5 py-2.5 rounded-xl border transition-all
                       {{ $val === 'all'
                          ? 'bg-primary-container text-background border-primary-container'
                          : 'text-on-surface-variant border-outline-variant/50 hover:border-outline hover:text-on-surface' }}">
            {{ $label }}
        </button>
        @endforeach
    </div>

    {{-- LIVE GAMES --}}
    @if($liveFixtures->count())
    <div class="mb-10 anim-on-scroll">
        <div class="flex items-center gap-3 mb-5">
            <div class="w-2 h-2 rounded-full bg-error animate-pulse"></div>
            <h2 class="font-display font-bold text-xl text-on-surface">Live Now</h2>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach($liveFixtures as $fixture)
            <div class="neo-surface rounded-2xl p-6 hover-lift border border-error/15" data-filter-target="games" data-filter-value="live">
                <div class="flex items-center justify-between mb-5">
                    <span class="badge-live">Live</span>
                    <span class="font-mono text-[10px] text-on-surface-variant tracking-wider uppercase">
                        {{ $fixture->tournament?->name ?? '' }} · MD{{ $fixture->matchday }}
                    </span>
                </div>
                <div class="flex items-center justify-between gap-4">
                    <div class="text-center flex-1 min-w-0">
                        <div class="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-2 text-sm font-black text-black"
                             style="background: {{ $fixture->homeTeam?->colour ?? '#00E676' }};">
                            {{ strtoupper(substr($fixture->homeTeam?->name ?? 'H', 0, 2)) }}
                        </div>
                        <div class="font-display font-bold text-sm text-on-surface truncate">{{ $fixture->homeTeam?->name ?? 'Home' }}</div>
                    </div>
                    <div class="flex-shrink-0 text-center">
                        <div class="font-display font-black text-3xl text-on-surface">
                            {{ $fixture->home_score ?? 0 }} – {{ $fixture->away_score ?? 0 }}
                        </div>
                        <div class="font-mono text-[9px] text-error mt-1 tracking-wider">LIVE</div>
                    </div>
                    <div class="text-center flex-1 min-w-0">
                        <div class="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-2 text-sm font-black text-black"
                             style="background: {{ $fixture->awayTeam?->colour ?? '#3B82F6' }};">
                            {{ strtoupper(substr($fixture->awayTeam?->name ?? 'A', 0, 2)) }}
                        </div>
                        <div class="font-display font-bold text-sm text-on-surface truncate">{{ $fixture->awayTeam?->name ?? 'Away' }}</div>
                    </div>
                </div>
                <div class="mt-5 pt-4 border-t border-outline-variant/15 flex items-center justify-between">
                    <span class="font-mono text-xs text-on-surface-variant">Matchday {{ $fixture->matchday }}</span>
                    @auth
                    <a href="{{ route('predictions.index') }}"
                       class="font-mono text-xs text-primary-container border border-primary-container/40 hover:border-primary-container px-4 py-2 rounded-lg transition-all">
                        My Picks
                    </a>
                    @else
                    <a href="{{ route('register') }}"
                       class="font-mono text-xs text-primary-container border border-primary-container/40 hover:border-primary-container px-4 py-2 rounded-lg transition-all">
                        Join Now
                    </a>
                    @endauth
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- UPCOMING GAMES --}}
    @if($upcomingFixtures->count())
    <div class="mb-10 anim-on-scroll">
        <h2 class="font-display font-bold text-xl text-on-surface mb-5">Upcoming Fixtures</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach($upcomingFixtures as $fixture)
            @php $isPostponed = $fixture->status->value === 'postponed'; @endphp
            <div class="neo-surface rounded-2xl p-6 hover-lift {{ $isPostponed ? 'opacity-70' : '' }}"
                 data-filter-target="games" data-filter-value="upcoming">
                <div class="flex items-center justify-between mb-5">
                    @if($isPostponed)
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[9px] font-mono font-bold uppercase tracking-widest border {{ $fixture->status->badgeClass() }}">
                            Postponed
                        </span>
                    @else
                        <span class="badge-upcoming">Upcoming</span>
                    @endif
                    <span class="font-mono text-[10px] text-on-surface-variant">
                        {{ $fixture->date ? $fixture->date->format('D · g:i A') : 'TBC' }}
                    </span>
                </div>
                <div class="flex items-center justify-between gap-4">
                    <div class="text-center flex-1 min-w-0">
                        <div class="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-2 text-sm font-black text-black"
                             style="background: {{ $fixture->homeTeam?->colour ?? '#00E676' }};">
                            {{ strtoupper(substr($fixture->homeTeam?->name ?? 'H', 0, 2)) }}
                        </div>
                        <div class="font-display font-bold text-sm text-on-surface truncate">{{ $fixture->homeTeam?->name ?? 'Home' }}</div>
                    </div>
                    <div class="flex-shrink-0 text-center">
                        <div class="font-display font-black text-2xl text-on-surface-variant">VS</div>
                        <div class="font-mono text-[9px] text-on-surface-variant mt-1 tracking-wider">
                            {{ $fixture->tournament?->name ?? '' }}
                        </div>
                    </div>
                    <div class="text-center flex-1 min-w-0">
                        <div class="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-2 text-sm font-black text-black"
                             style="background: {{ $fixture->awayTeam?->colour ?? '#3B82F6' }};">
                            {{ strtoupper(substr($fixture->awayTeam?->name ?? 'A', 0, 2)) }}
                        </div>
                        <div class="font-display font-bold text-sm text-on-surface truncate">{{ $fixture->awayTeam?->name ?? 'Away' }}</div>
                    </div>
                </div>
                <div class="mt-5 pt-4 border-t border-outline-variant/15 flex items-center justify-between">
                    <div>
                        <div class="font-mono text-[10px] text-on-surface-variant">Matchday</div>
                        <div class="font-mono text-xs text-secondary-container font-bold">MD{{ $fixture->matchday }}</div>
                    </div>
                    @auth
                    <a href="{{ route('squad.builder') }}"
                       class="font-mono text-xs text-primary-container border border-primary-container/40 hover:border-primary-container px-4 py-2 rounded-lg transition-all">
                        Set Lineup
                    </a>
                    @else
                    <a href="{{ route('register') }}"
                       class="font-mono text-xs text-primary-container border border-primary-container/40 hover:border-primary-container px-4 py-2 rounded-lg transition-all">
                        Join Now
                    </a>
                    @endauth
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- COMPLETED GAMES --}}
    @if($completedFixtures->count())
    <div class="anim-on-scroll">
        <h2 class="font-display font-bold text-xl text-on-surface mb-5">Recent Results</h2>
        <div class="neo-surface rounded-2xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-outline-variant/20" style="background: rgba(0,0,0,0.2);">
                            <th class="font-mono text-[10px] text-on-surface-variant text-left px-6 py-3.5 tracking-widest uppercase">Match</th>
                            <th class="font-mono text-[10px] text-on-surface-variant text-center px-4 py-3.5 tracking-widest uppercase">Score</th>
                            <th class="font-mono text-[10px] text-on-surface-variant text-left px-4 py-3.5 tracking-widest uppercase hidden sm:table-cell">Competition</th>
                            <th class="font-mono text-[10px] text-on-surface-variant text-left px-4 py-3.5 tracking-widest uppercase hidden md:table-cell">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/10">
                        @foreach($completedFixtures as $fixture)
                        <tr class="table-row-hover transition-colors" data-filter-target="games" data-filter-value="completed">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <span class="w-2 h-2 rounded-full flex-shrink-0"
                                          style="background: {{ $fixture->homeTeam?->colour ?? '#00E676' }};"></span>
                                    <span class="font-display font-semibold text-sm text-on-surface">
                                        {{ $fixture->homeTeam?->name ?? 'Home' }} vs {{ $fixture->awayTeam?->name ?? 'Away' }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-4 text-center">
                                <span class="font-display font-black text-base text-on-surface">
                                    {{ $fixture->home_score ?? 0 }}–{{ $fixture->away_score ?? 0 }}
                                </span>
                            </td>
                            <td class="px-4 py-4 hidden sm:table-cell">
                                <span class="font-mono text-xs text-on-surface-variant">
                                    {{ $fixture->tournament?->name ?? '—' }} · MD{{ $fixture->matchday }}
                                </span>
                            </td>
                            <td class="px-4 py-4 hidden md:table-cell">
                                <span class="font-mono text-xs text-on-surface-variant">
                                    {{ $fixture->date?->format('d M, Y') ?? '—' }}
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    {{-- Empty state --}}
    @if(!$liveFixtures->count() && !$upcomingFixtures->count() && !$completedFixtures->count())
    <div class="text-center py-20 anim-on-scroll">
        <span class="material-symbols-outlined text-5xl text-on-surface-variant/20 block mb-4">sports_soccer</span>
        <h2 class="font-display font-bold text-2xl text-on-surface mb-2">No fixtures yet</h2>
        <p class="text-on-surface-variant text-sm max-w-sm mx-auto mb-6">Check back soon — fixtures will appear here once they're scheduled.</p>
        @guest
        <a href="{{ route('register') }}"
           class="inline-flex items-center gap-2 bg-primary-container text-background font-mono font-bold text-sm tracking-wider uppercase px-8 py-3.5 rounded-xl hover:bg-primary-fixed transition-all">
            Create Account
        </a>
        @endguest
    </div>
    @endif

</div>
@endsection
