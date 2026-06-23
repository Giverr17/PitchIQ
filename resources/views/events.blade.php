@extends('layouts.app')
@section('title', 'Events')
@section('meta_description', 'Discover upcoming PitchIQ campus events — Faculty Cup, Departmental League, Special Tournaments and more.')

@section('content')
<div class="max-w-7xl mx-auto px-5 sm:px-8 py-12">

    {{-- Header --}}
    <div class="mb-10 anim-on-scroll">
        <p class="font-mono text-xs text-secondary-container tracking-widest uppercase mb-2">Campus Calendar</p>
        <h1 class="font-display font-black text-5xl text-on-surface tracking-tight mb-3">Events</h1>
        <p class="text-on-surface-variant max-w-xl">Register for campus football events, tournaments and special challenges — all with real prize pools.</p>
    </div>

    {{-- Filter Tabs --}}
    <div class="flex items-center gap-2 flex-wrap mb-10 anim-on-scroll" data-filter-group="events">
        @foreach(['all' => 'All Events', 'faculty-cup' => 'Faculty Cup', 'dept-league' => 'Dept League', 'special' => 'Special Events', 'completed' => 'Completed'] as $val => $label)
        <button data-filter="{{ $val }}"
                class="font-mono text-xs tracking-wider uppercase px-5 py-2.5 rounded-xl border transition-all
                       {{ $val === 'all'
                          ? 'bg-primary-container text-background border-primary-container'
                          : 'text-on-surface-variant border-outline-variant/50 hover:border-outline hover:text-on-surface' }}">
            {{ $label }}
        </button>
        @endforeach
    </div>

    @if($featured)
    {{-- Featured Tournament (Hero Card) --}}
    @php
        $featuredFilter = $featured->status === \App\Enums\TournamentStatus::Completed
            ? 'completed'
            : match($featured->type) {
                \App\Enums\TournamentType::FacultyCup         => 'faculty-cup',
                \App\Enums\TournamentType::DepartmentalLeague => 'dept-league',
                default                                       => 'special',
            };
    @endphp
    <div class="neo-surface rounded-2xl overflow-hidden mb-10 anim-on-scroll"
         style="background: linear-gradient(135deg, #0d1f13 0%, #121714 100%); border-color: rgba(0,230,118,0.2);"
         data-filter-target="events" data-filter-value="{{ $featuredFilter }}">
        <div class="flex flex-col lg:flex-row">

            {{-- Left Info --}}
            <div class="flex-1 p-8 lg:p-12">
                <div class="flex items-center gap-3 mb-6 flex-wrap">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-[9px] font-mono font-bold uppercase tracking-widest border {{ $featured->status->badgeClass() }}">
                        {{ $featured->status->label() }}
                    </span>
                    <span class="inline-flex items-center gap-1.5 text-[11px] font-mono font-bold tracking-wider border rounded-full px-3 py-1 text-secondary-container bg-secondary-container/10 border-secondary-container/25">
                        🏆 {{ $featured->type->label() }}
                    </span>
                </div>
                <h2 class="font-display font-black text-4xl md:text-5xl text-on-surface tracking-tight mb-2">
                    {{ $featured->name }}
                </h2>
                <p class="font-mono text-sm text-on-surface-variant mb-8">Season {{ $featured->season }}</p>

                <div class="grid grid-cols-2 sm:grid-cols-3 gap-6 mb-8">
                    <div>
                        <div class="flex items-center gap-1.5 mb-1">
                            <span class="material-symbols-outlined text-on-surface-variant text-[14px]">calendar_month</span>
                            <span class="font-mono text-[10px] text-on-surface-variant tracking-wider uppercase">Start Date</span>
                        </div>
                        <div class="font-display font-bold text-sm text-on-surface">
                            {{ $featured->start_date ? $featured->start_date->format('d M, Y') : 'TBC' }}
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center gap-1.5 mb-1">
                            <span class="material-symbols-outlined text-on-surface-variant text-[14px]">group</span>
                            <span class="font-mono text-[10px] text-on-surface-variant tracking-wider uppercase">Teams</span>
                        </div>
                        <div class="font-display font-bold text-sm text-on-surface">{{ $featured->teams_count }} registered</div>
                    </div>
                    <div>
                        <div class="flex items-center gap-1.5 mb-1">
                            <span class="material-symbols-outlined text-on-surface-variant text-[14px]">sports_soccer</span>
                            <span class="font-mono text-[10px] text-on-surface-variant tracking-wider uppercase">Squads Built</span>
                        </div>
                        <div class="font-display font-bold text-sm text-on-surface">{{ $featured->fantasy_teams_count }}</div>
                    </div>
                </div>

                @if($featured->status !== \App\Enums\TournamentStatus::Completed)
                <a href="{{ route('register') }}"
                   class="inline-flex items-center gap-2 bg-primary-container text-background font-mono font-bold
                          text-sm tracking-wider uppercase px-8 py-3.5 rounded-xl hover:bg-primary-fixed
                          transition-all glow-green">
                    <span class="material-symbols-outlined text-[18px]">add_circle</span>
                    Register Fantasy Team
                </a>
                @endif
            </div>

            {{-- Right Stats --}}
            <div class="lg:w-72 border-t lg:border-t-0 lg:border-l border-outline-variant/20 p-8 flex flex-col justify-center gap-5">
                <div class="text-center">
                    <div class="font-mono text-[10px] text-on-surface-variant tracking-widest uppercase mb-1">Teams</div>
                    <div class="font-display font-black text-5xl text-primary-container">{{ $featured->teams_count }}</div>
                </div>
                <div class="h-px bg-outline-variant/20"></div>
                <div class="text-center">
                    <div class="font-mono text-[10px] text-on-surface-variant tracking-widest uppercase mb-1">Squads Built</div>
                    <div class="font-display font-black text-4xl text-on-surface">{{ $featured->fantasy_teams_count }}</div>
                </div>
                @if($featuredNextFixture)
                <div class="h-px bg-outline-variant/20"></div>
                <div class="text-center">
                    <div class="font-mono text-[10px] text-on-surface-variant tracking-widest uppercase mb-1">Next Match</div>
                    <div class="font-display font-bold text-xl text-secondary-container">
                        {{ $featuredNextFixture->date ? $featuredNextFixture->date->format('d M · H:i') : 'TBC' }}
                    </div>
                </div>
                @endif
            </div>

        </div>
    </div>
    @endif

    {{-- Event Grid --}}
    @if($eventCards->count())
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($eventCards as $tournament)
        @php
            $filterValue = $tournament->status === \App\Enums\TournamentStatus::Completed
                ? 'completed'
                : match($tournament->type) {
                    \App\Enums\TournamentType::FacultyCup         => 'faculty-cup',
                    \App\Enums\TournamentType::DepartmentalLeague => 'dept-league',
                    default                                       => 'special',
                };
            $typeEmoji = match($tournament->type->value) {
                'faculty_cup'         => '🏆',
                'departmental_league' => '🎓',
                default               => '⚡',
            };
        @endphp
        <div class="neo-surface rounded-2xl overflow-hidden hover-lift anim-on-scroll {{ $tournament->status->value === 'completed' ? 'opacity-70' : '' }}"
             data-filter-target="events" data-filter-value="{{ $filterValue }}">
            <div class="p-6">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex flex-col gap-2">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[9px] font-mono font-bold uppercase tracking-widest border {{ $tournament->status->badgeClass() }}">
                            {{ $tournament->status->label() }}
                        </span>
                        <span class="inline-flex items-center text-[10px] font-mono font-bold tracking-wider border rounded-full px-2.5 py-1 text-secondary-container bg-secondary-container/10 border-secondary-container/25">
                            {{ $tournament->type->label() }}
                        </span>
                    </div>
                    <span class="text-2xl">{{ $typeEmoji }}</span>
                </div>
                <h3 class="font-display font-bold text-lg text-on-surface mb-1">{{ $tournament->name }}</h3>
                <p class="text-on-surface-variant text-sm leading-relaxed mb-5 font-mono">Season {{ $tournament->season }}</p>
                <div class="space-y-2">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-on-surface-variant text-[15px]">calendar_month</span>
                        <span class="font-mono text-xs text-on-surface-variant">
                            {{ $tournament->start_date ? $tournament->start_date->format('d M, Y') : 'TBC' }}
                        </span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-on-surface-variant text-[15px]">group</span>
                        <span class="font-mono text-xs text-on-surface-variant">{{ $tournament->teams_count }} teams registered</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-on-surface-variant text-[15px]">sports_soccer</span>
                        <span class="font-mono text-xs text-on-surface-variant">{{ $tournament->fantasy_teams_count }} squads built</span>
                    </div>
                </div>
            </div>
            <div class="border-t border-outline-variant/15 px-6 py-4 flex items-center justify-between">
                <div>
                    <div class="font-mono text-[10px] text-on-surface-variant">Season</div>
                    <div class="font-display font-bold text-sm text-primary-container">{{ $tournament->season }}</div>
                </div>
                @if($tournament->status->value !== 'completed')
                <a href="{{ route('register') }}"
                   class="font-mono text-xs text-primary-container hover:text-primary border border-primary-container/40
                          hover:border-primary-container px-4 py-2 rounded-lg transition-all">
                    Register
                </a>
                @else
                <span class="font-mono text-xs text-on-surface-variant/50 border border-outline-variant/20 px-4 py-2 rounded-lg">
                    Ended
                </span>
                @endif
            </div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- Empty state --}}
    @if(!$featured && $eventCards->isEmpty())
    <div class="text-center py-20 anim-on-scroll">
        <span class="material-symbols-outlined text-5xl text-on-surface-variant/20 block mb-4">emoji_events</span>
        <h2 class="font-display font-bold text-2xl text-on-surface mb-2">No events yet</h2>
        <p class="text-on-surface-variant text-sm max-w-sm mx-auto">Events will appear here once tournaments are created.</p>
    </div>
    @endif

</div>
@endsection

@push('ads')
    @include('partials.propeller-ad')
@endpush
