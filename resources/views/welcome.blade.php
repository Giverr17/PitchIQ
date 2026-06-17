@extends('layouts.app')

@section('title', 'PitchIQ')
@section('meta_description', 'PitchIQ — Own Your Squad. Rule the Campus. The first-ever free fantasy football platform for Nigerian universities. Draft players, score points, win real prizes.')

@section('content')

{{-- ══════════════════════════════════════════════
     HERO SECTION
     ══════════════════════════════════════════════ --}}
<section class="relative min-h-[calc(100vh-70px)] flex items-center overflow-hidden">

    {{-- Background grid --}}
    <div class="absolute inset-0 pitch-pattern opacity-30 pointer-events-none"></div>

    {{-- Radial green glow center-right --}}
    <div class="absolute top-1/2 right-0 -translate-y-1/2 w-[600px] h-[600px] rounded-full pointer-events-none"
         style="background: radial-gradient(circle, rgba(0,230,118,0.07) 0%, transparent 70%);"></div>

    <div class="max-w-7xl mx-auto px-5 sm:px-8 w-full py-16 lg:py-0">
        <div class="flex flex-col lg:flex-row items-center gap-12 lg:gap-8">

            {{-- ── Left: Copy ── --}}
            <div class="w-full lg:w-[52%] space-y-7 anim-on-scroll">

                {{-- Live Badge --}}
                <div class="badge-live inline-flex">MATCH TOKENS ACTIVE</div>

                {{-- Headline --}}
                <h1 class="font-display font-black text-5xl sm:text-6xl lg:text-7xl leading-[1.05] tracking-tight text-on-surface">
                    Own Your<br />
                    Squad.<br />
                    <span class="text-gradient">Rule the</span><br />
                    Campus.
                </h1>

                {{-- Sub --}}
                <p class="text-on-surface-variant text-lg leading-relaxed max-w-[480px]">
                    Draft real student players for individual departmental &amp; level clashes. Enter games using tokens (10-50 per match). Watch ads for free tokens or top up to play.
                </p>

                {{-- CTAs --}}
                <div class="flex flex-col sm:flex-row gap-4 pt-2">
                    <a href="{{ Auth::check() ? route('dashboard') : route('register') }}"
                       id="hero-cta-primary"
                       class="inline-flex items-center justify-center gap-2 bg-primary-container text-background
                              font-mono font-bold text-sm tracking-wider uppercase px-8 py-4 rounded-xl
                              hover:bg-primary-fixed transition-colors glow-green animate-pulse-glow">
                        <span class="material-symbols-outlined text-[18px]">add_circle</span>
                        {{ Auth::check() ? 'Go To Dashboard' : 'Draft Your Team' }}
                    </a>
                    <a href="{{ route('how-it-works') }}"
                       id="hero-cta-secondary"
                       class="inline-flex items-center justify-center gap-2 border border-outline-variant/60
                              text-on-surface-variant font-mono font-semibold text-sm tracking-wider uppercase
                              px-8 py-4 rounded-xl hover:border-primary-container/50 hover:text-primary-container
                              transition-all">
                        <span class="material-symbols-outlined text-[18px]">play_circle</span>
                        See How It Works
                    </a>
                </div>

                {{-- Mini stats --}}
                <div class="grid grid-cols-3 gap-6 pt-6 border-t border-outline-variant/20">
                    <div>
                        <div class="font-display font-black text-2xl text-on-surface">13+</div>
                        <div class="font-mono text-[11px] text-on-surface-variant tracking-wider uppercase mt-1">Faculties</div>
                    </div>
                    <div>
                        <div class="font-display font-black text-2xl text-on-surface">10-50</div>
                        <div class="font-mono text-[11px] text-on-surface-variant tracking-wider uppercase mt-1">Tokens/Game</div>
                    </div>
                    <div>
                        <div class="font-display font-black text-2xl text-on-surface">Real</div>
                        <div class="font-mono text-[11px] text-on-surface-variant tracking-wider uppercase mt-1">Match Prizes</div>
                    </div>
                </div>
            </div>

            {{-- ── Right: Pitch Mockup ── --}}
            {{-- Horizontal padding on mobile/tablet creates room for the floating chips inside the column --}}
            <div class="w-full lg:w-[48%] relative flex justify-center items-center anim-on-scroll anim-delay-2 px-10 sm:px-14 lg:px-6">

                {{-- Main pitch card --}}
                <div class="relative w-full max-w-[320px] sm:max-w-[360px] neo-surface rounded-2xl p-2 shadow-2xl glow-green animate-float-pitch z-10">
                    <div class="w-full aspect-[3/4] rounded-[14px] overflow-hidden relative"
                         style="background: linear-gradient(180deg, #061209 0%, #0a1a10 100%);">

                        <div class="absolute inset-0 pitch-pattern opacity-50"></div>
                        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2
                                    w-24 h-24 rounded-full border border-primary-container/20 opacity-60"></div>
                        <div class="absolute top-1/2 left-0 right-0 h-[1px] bg-primary-container/15"></div>

                        {{-- Forwards --}}
                        <div class="absolute top-[14%] left-[18%] -translate-x-1/2 -translate-y-1/2">
                            <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-full bg-surface border-2 border-primary-container/80 flex flex-col items-center justify-center shadow-lg">
                                <span class="font-mono text-[7px] sm:text-[8px] text-primary-container font-bold">FWD</span>
                                <span class="font-mono text-[6px] sm:text-[7px] text-on-surface-variant">Emeka</span>
                            </div>
                        </div>
                        <div class="absolute top-[14%] left-[50%] -translate-x-1/2 -translate-y-1/2">
                            <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-full bg-surface border-2 border-primary-container/80 flex flex-col items-center justify-center shadow-lg">
                                <span class="font-mono text-[7px] sm:text-[8px] text-primary-container font-bold">FWD</span>
                                <span class="font-mono text-[6px] sm:text-[7px] text-on-surface-variant">Kalu</span>
                            </div>
                        </div>
                        <div class="absolute top-[14%] right-[18%] translate-x-1/2 -translate-y-1/2">
                            <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-full bg-surface border-2 border-primary-container/80 flex flex-col items-center justify-center shadow-lg">
                                <span class="font-mono text-[7px] sm:text-[8px] text-primary-container font-bold">FWD</span>
                                <span class="font-mono text-[6px] sm:text-[7px] text-on-surface-variant">Tunde</span>
                            </div>
                        </div>
                        {{-- Midfielders --}}
                        <div class="absolute top-[38%] left-[20%] -translate-x-1/2 -translate-y-1/2">
                            <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-full bg-surface border-2 border-secondary-container/80 flex flex-col items-center justify-center shadow-lg">
                                <span class="font-mono text-[7px] sm:text-[8px] text-secondary-container font-bold">MID</span>
                                <span class="font-mono text-[6px] sm:text-[7px] text-on-surface-variant">Ade</span>
                            </div>
                        </div>
                        <div class="absolute top-[38%] left-[50%] -translate-x-1/2 -translate-y-1/2">
                            <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-full bg-surface border-[2px] border-secondary-container flex flex-col items-center justify-center shadow-lg"
                                 style="box-shadow: 0 0 14px rgba(253,212,0,0.3);">
                                <span class="font-mono text-[6px] sm:text-[7px] text-secondary-container font-bold leading-tight">MID (C)</span>
                                <span class="font-mono text-[6px] sm:text-[7px] text-on-surface-variant">Bello</span>
                            </div>
                        </div>
                        <div class="absolute top-[38%] right-[20%] translate-x-1/2 -translate-y-1/2">
                            <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-full bg-surface border-2 border-secondary-container/80 flex flex-col items-center justify-center shadow-lg">
                                <span class="font-mono text-[7px] sm:text-[8px] text-secondary-container font-bold">MID</span>
                                <span class="font-mono text-[6px] sm:text-[7px] text-on-surface-variant">Uche</span>
                            </div>
                        </div>
                        {{-- Defenders --}}
                        <div class="absolute top-[62%] left-[15%] -translate-x-1/2 -translate-y-1/2">
                            <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-full bg-surface border border-outline/60 flex flex-col items-center justify-center shadow-lg">
                                <span class="font-mono text-[6px] sm:text-[7px] text-on-surface-variant font-bold">DEF</span>
                            </div>
                        </div>
                        <div class="absolute top-[62%] left-[37%] -translate-x-1/2 -translate-y-1/2">
                            <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-full bg-surface border border-outline/60 flex flex-col items-center justify-center shadow-lg">
                                <span class="font-mono text-[6px] sm:text-[7px] text-on-surface-variant font-bold">DEF</span>
                            </div>
                        </div>
                        <div class="absolute top-[62%] right-[37%] translate-x-1/2 -translate-y-1/2">
                            <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-full bg-surface border border-outline/60 flex flex-col items-center justify-center shadow-lg">
                                <span class="font-mono text-[6px] sm:text-[7px] text-on-surface-variant font-bold">DEF</span>
                            </div>
                        </div>
                        <div class="absolute top-[62%] right-[15%] translate-x-1/2 -translate-y-1/2">
                            <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-full bg-surface border border-outline/60 flex flex-col items-center justify-center shadow-lg">
                                <span class="font-mono text-[6px] sm:text-[7px] text-on-surface-variant font-bold">DEF</span>
                            </div>
                        </div>
                        {{-- Keeper --}}
                        <div class="absolute top-[84%] left-1/2 -translate-x-1/2 -translate-y-1/2">
                            <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-full bg-surface border-2 border-error/60 flex flex-col items-center justify-center shadow-lg">
                                <span class="font-mono text-[7px] sm:text-[8px] text-error font-bold">GK</span>
                                <span class="font-mono text-[6px] sm:text-[7px] text-on-surface-variant">Seun</span>
                            </div>
                        </div>
                    </div>

                    {{-- Formation label --}}
                    <div class="flex items-center justify-between px-3 py-2">
                        <span class="font-mono text-[10px] text-on-surface-variant">4-3-3 Formation</span>
                        <span class="font-mono text-[10px] text-primary-container">ENG FC</span>
                    </div>
                </div>

                {{-- Floating chips — safe within horizontal padding of parent --}}
                {{-- Live score chip — top right --}}
                <div class="absolute top-4 right-0 glass rounded-xl p-2.5 shadow-xl z-20 animate-float"
                     style="animation-delay: 0.5s;">
                    <div class="flex items-center gap-2 mb-1">
                        <div class="w-2 h-2 rounded-full bg-error animate-pulse"></div>
                        <span class="font-mono text-[9px] text-error font-bold tracking-wider">LIVE</span>
                    </div>
                    <div class="font-display font-bold text-sm text-primary-container">ENG 2 – 1 MED</div>
                    <div class="font-mono text-[8px] text-on-surface-variant mt-0.5">75' · Faculty Cup GF</div>
                </div>

                {{-- Points chip — bottom left --}}
                <div class="absolute bottom-6 left-0 glass rounded-xl p-2.5 shadow-xl z-20 animate-float"
                     style="animation-delay: 1.5s;">
                    <div class="font-mono text-[9px] text-on-surface-variant tracking-wider uppercase mb-1">Latest Action</div>
                    <div class="font-display font-black text-xl text-gradient-gold">+45 PTS</div>
                    <div class="font-mono text-[8px] text-on-surface-variant mt-0.5">Goal + Assist · Bello MID</div>
                </div>

                {{-- Rank chip — mid left --}}
                <div class="absolute top-1/2 left-0 -translate-y-1/2 glass rounded-xl p-2.5 shadow-xl z-20 animate-float"
                     style="animation-delay: 1s;">
                    <div class="font-mono text-[9px] text-on-surface-variant tracking-wider uppercase mb-1">Campus Rank</div>
                    <div class="font-display font-black text-xl text-on-surface">#3</div>
                    <div class="font-mono text-[8px] text-primary-container mt-0.5">↑ 2 positions</div>
                </div>
            </div>

        </div>
    </div>
</section>

{{-- ══════════════════════════════════════════════
     STATS BAR
     ══════════════════════════════════════════════ --}}
<section class="border-y border-outline-variant/15" style="background: #0e1511;">
    <div class="max-w-7xl mx-auto px-5 sm:px-8">
        <div class="grid grid-cols-2 md:grid-cols-4">
            @foreach([
                ['count' => 620, 'suffix' => '+', 'label' => 'Active Players', 'icon' => 'group'],
                ['count' => 13,  'suffix' => '+', 'label' => 'Faculties',      'icon' => 'school'],
                ['count' => 94,  'suffix' => '',  'label' => 'Games Played',   'icon' => 'sports_soccer'],
                ['count' => 500, 'suffix' => 'k', 'label' => 'Prize Pool (₦)', 'icon' => 'emoji_events'],
            ] as $i => $stat)
            <div class="flex flex-col sm:flex-row items-center sm:items-start gap-3 py-8 px-6
                        anim-on-scroll border-outline-variant/15
                        {{ $i % 2 === 0 ? 'border-r' : '' }}
                        {{ $i < 2 ? 'border-b md:border-b-0' : '' }}
                        md:border-b-0 md:border-r md:[&:nth-child(4)]:border-r-0
                        {{ $i > 0 ? 'anim-delay-' . $i : '' }}">
                <div class="w-10 h-10 rounded-xl bg-primary-container/10 flex items-center justify-center flex-shrink-0">
                    <span class="material-symbols-outlined text-primary-container text-[20px]">{{ $stat['icon'] }}</span>
                </div>
                <div class="text-center sm:text-left">
                    <div class="font-display font-black text-3xl text-on-surface"
                         data-count="{{ $stat['count'] }}" data-suffix="{{ $stat['suffix'] }}">
                        {{ $stat['count'] }}{{ $stat['suffix'] }}
                    </div>
                    <div class="font-mono text-[11px] text-on-surface-variant tracking-wider uppercase mt-0.5">
                        {{ $stat['label'] }}
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ══════════════════════════════════════════════
     HOW IT WORKS — TEASER
     ══════════════════════════════════════════════ --}}
<section class="max-w-7xl mx-auto px-5 sm:px-8 py-24">
    <div class="text-center mb-14 anim-on-scroll">
        <p class="font-mono text-xs text-primary-container tracking-widest uppercase mb-3">Simple as 1-2-3</p>
        <h2 class="font-display font-black text-4xl md:text-5xl text-on-surface tracking-tight">How PitchIQ Works</h2>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        @foreach([
            ['step' => '01', 'icon' => 'toll', 'title' => 'Get Entry Tokens', 'desc' => 'Earn 10-50 tokens by watching short ads, or buy token packs to enter matches instantly.', 'delay' => ''],
            ['step' => '02', 'icon' => 'groups', 'title' => 'Draft Fresh Squad', 'desc' => 'No long-term transfers. Select your players from the competing departments specifically for that single match.', 'delay' => 'anim-delay-2'],
            ['step' => '03', 'icon' => 'emoji_events', 'title' => 'Win Match Prizes', 'desc' => 'Track your live stats. The top manager of each departmental match claims the designated prize pool.', 'delay' => 'anim-delay-3'],
        ] as $item)
        <div class="neo-surface rounded-2xl p-8 relative hover-lift anim-on-scroll {{ $item['delay'] }}">
            <div class="absolute top-6 right-6 font-display font-black text-6xl text-primary-container/8 leading-none select-none">
                {{ $item['step'] }}
            </div>
            <div class="w-12 h-12 rounded-xl bg-primary-container/10 border border-primary-container/20
                        flex items-center justify-center mb-6">
                <span class="material-symbols-outlined text-primary-container text-[22px]">{{ $item['icon'] }}</span>
            </div>
            <h3 class="font-display font-bold text-xl text-on-surface mb-3">{{ $item['title'] }}</h3>
            <p class="text-on-surface-variant text-sm leading-relaxed">{{ $item['desc'] }}</p>
        </div>
        @endforeach
    </div>

    <div class="text-center mt-10">
        <a href="{{ route('how-it-works') }}"
           class="inline-flex items-center gap-2 font-mono text-sm text-primary-container hover:text-primary
                  tracking-wider uppercase transition-colors">
            Full Guide
            <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
        </a>
    </div>
</section>

{{-- ══════════════════════════════════════════════
     UPCOMING FIXTURES
     ══════════════════════════════════════════════ --}}
<section class="py-24 border-t border-outline-variant/10" style="background: #0b0f0c;">
    <div class="max-w-7xl mx-auto px-5 sm:px-8">
        <div class="flex items-end justify-between mb-12">
            <div class="anim-on-scroll">
                <p class="font-mono text-xs text-primary-container tracking-widest uppercase mb-2">Real matches · Real stakes</p>
                <h2 class="font-display font-black text-4xl text-on-surface tracking-tight">Upcoming Fixtures</h2>
            </div>
            <a href="{{ Auth::check() ? route('predictions.index') : route('register') }}"
               class="hidden sm:inline-flex items-center gap-2 font-mono text-xs text-on-surface-variant
                      hover:text-primary-container tracking-wider uppercase transition-colors anim-on-scroll">
                Predict & Win
                <span class="material-symbols-outlined text-[14px]">arrow_forward</span>
            </a>
        </div>

        @if($welcomeFixtures->isNotEmpty())
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($welcomeFixtures as $i => $fixture)
                    @php
                        $isLive      = $fixture->status->value === 'live';
                        $homeColour  = $fixture->homeTeam?->colour ?? '#00E676';
                        $awayColour  = $fixture->awayTeam?->colour ?? '#3B82F6';
                        $homeName    = $fixture->homeTeam?->name   ?? 'TBD';
                        $awayName    = $fixture->awayTeam?->name   ?? 'TBD';
                        $tournament  = $fixture->tournament?->name ?? '';
                        $dateLabel   = $fixture->date
                            ? \Carbon\Carbon::parse($fixture->date)->format('d M · H:i')
                            : 'TBC';
                        $delays      = ['', 'anim-delay-1', 'anim-delay-2', 'anim-delay-3', 'anim-delay-3', 'anim-delay-3'];
                    @endphp
                    <div class="neo-surface rounded-2xl p-6 hover-lift anim-on-scroll {{ $delays[$i] ?? '' }}">

                        {{-- Status + time --}}
                        <div class="flex items-center justify-between mb-4">
                            @if($isLive)
                                <span class="badge-live">Live</span>
                            @else
                                <span class="badge-upcoming">Upcoming</span>
                            @endif
                            <span class="font-mono text-xs text-on-surface-variant">{{ $dateLabel }}</span>
                        </div>

                        {{-- Teams --}}
                        <div class="flex items-center justify-between gap-3 my-5">
                            {{-- Home --}}
                            <div class="text-center flex-1 min-w-0">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center mx-auto mb-2 font-black text-xs border-2"
                                     style="background: {{ $homeColour }}18; border-color: {{ $homeColour }}60; color: {{ $homeColour }};">
                                    {{ strtoupper(substr($homeName, 0, 2)) }}
                                </div>
                                <div class="font-display font-bold text-sm text-on-surface truncate">{{ $homeName }}</div>
                            </div>

                            {{-- Separator --}}
                            <div class="flex flex-col items-center flex-shrink-0">
                                @if($isLive)
                                    <div class="font-display font-black text-3xl text-on-surface">
                                        {{ $fixture->home_score ?? 0 }} <span class="text-outline">–</span> {{ $fixture->away_score ?? 0 }}
                                    </div>
                                @else
                                    <div class="font-display font-black text-2xl text-on-surface-variant">VS</div>
                                @endif
                                <div class="font-mono text-[9px] text-on-surface-variant/50 mt-1 tracking-wider uppercase">
                                    MD{{ $fixture->matchday }}
                                    @if($tournament) · {{ $tournament }} @endif
                                </div>
                            </div>

                            {{-- Away --}}
                            <div class="text-center flex-1 min-w-0">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center mx-auto mb-2 font-black text-xs border-2"
                                     style="background: {{ $awayColour }}18; border-color: {{ $awayColour }}60; color: {{ $awayColour }};">
                                    {{ strtoupper(substr($awayName, 0, 2)) }}
                                </div>
                                <div class="font-display font-bold text-sm text-on-surface truncate">{{ $awayName }}</div>
                            </div>
                        </div>

                        {{-- Footer --}}
                        <div class="border-t border-outline-variant/15 pt-4 flex items-center justify-between">
                            <div>
                                <div class="font-mono text-[10px] text-on-surface-variant/50">
                                    {{ $isLive ? 'In Progress' : 'Squad deadline' }}
                                </div>
                                <div class="font-display font-bold text-[13px]"
                                     style="color: {{ $isLive ? '#ef4444' : '#00E676' }};">
                                    {{ $isLive ? 'Live now' : $dateLabel }}
                                </div>
                            </div>
                            <a href="{{ Auth::check() ? route('squad.builder') : route('register') }}"
                               class="font-mono text-xs text-primary-container hover:text-primary border border-primary-container/40
                                      hover:border-primary-container px-4 py-2 rounded-lg transition-all">
                                {{ $isLive ? 'Track Game' : 'Set Lineup' }}
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            {{-- Empty state — no fixtures scheduled yet --}}
            <div class="rounded-2xl border border-outline-variant/15 p-14 text-center" style="background: rgba(13,17,15,0.7);">
                <span class="material-symbols-outlined text-4xl text-on-surface-variant/20 block mb-3">calendar_month</span>
                <h3 class="font-display font-black text-lg text-white mb-2">No Fixtures Yet</h3>
                <p class="font-mono text-xs text-on-surface-variant/40">Upcoming matches will appear here once the admin schedules them.</p>
            </div>
        @endif
    </div>
</section>

{{-- ══════════════════════════════════════════════
     EVENTS PREVIEW
     ══════════════════════════════════════════════ --}}
<section class="max-w-7xl mx-auto px-5 sm:px-8 py-24">
    <div class="flex items-end justify-between mb-12">
        <div class="anim-on-scroll">
            <p class="font-mono text-xs text-secondary-container tracking-widest uppercase mb-2">Campus calendar</p>
            <h2 class="font-display font-black text-4xl text-on-surface tracking-tight">Upcoming Events</h2>
        </div>
        <a href="{{ route('events') }}"
           class="hidden sm:inline-flex items-center gap-2 font-mono text-xs text-on-surface-variant
                  hover:text-primary-container tracking-wider uppercase transition-colors anim-on-scroll">
            View All
            <span class="material-symbols-outlined text-[14px]">arrow_forward</span>
        </a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        @foreach([
            ['emoji' => '🏆', 'cat' => 'Faculty Cup', 'cat_color' => 'text-primary-container', 'cat_bg' => 'bg-primary-container/10 border-primary-container/25', 'title' => 'Inter-Faculty Championship', 'date' => 'Jun 14, 2026', 'venue' => 'Campus Main Pitch', 'prize' => '₦150,000', 'participants' => 13, 'delay' => ''],
            ['emoji' => '⚡', 'cat' => 'Special Event', 'cat_color' => 'text-secondary-container', 'cat_bg' => 'bg-secondary-container/10 border-secondary-container/25', 'title' => 'Weekend Blitz Tournament', 'date' => 'Jun 21, 2026', 'venue' => 'Sports Complex', 'prize' => '₦80,000', 'participants' => 32, 'delay' => 'anim-delay-2'],
            ['emoji' => '🎓', 'cat' => 'Dept League', 'cat_color' => 'text-tertiary', 'cat_bg' => 'bg-tertiary/10 border-tertiary/25', 'title' => 'Departmental Showdown S2', 'date' => 'Jul 5, 2026', 'venue' => 'Multiple Venues', 'prize' => '₦60,000', 'participants' => 48, 'delay' => 'anim-delay-3'],
        ] as $ev)
        <div class="neo-surface rounded-2xl overflow-hidden hover-lift anim-on-scroll {{ $ev['delay'] }}">
            <div class="p-6">
                <div class="flex items-start justify-between mb-4">
                    <span class="inline-flex items-center gap-1.5 text-[11px] font-mono font-bold tracking-wider
                                 border rounded-full px-3 py-1 {{ $ev['cat_color'] }} {{ $ev['cat_bg'] }}">
                        {{ $ev['cat'] }}
                    </span>
                    <span class="text-2xl">{{ $ev['emoji'] }}</span>
                </div>
                <h3 class="font-display font-bold text-lg text-on-surface mb-4">{{ $ev['title'] }}</h3>
                <div class="space-y-2">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-on-surface-variant text-[16px]">calendar_month</span>
                        <span class="font-mono text-xs text-on-surface-variant">{{ $ev['date'] }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-on-surface-variant text-[16px]">location_on</span>
                        <span class="font-mono text-xs text-on-surface-variant">{{ $ev['venue'] }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-on-surface-variant text-[16px]">group</span>
                        <span class="font-mono text-xs text-on-surface-variant">{{ $ev['participants'] }} teams registered</span>
                    </div>
                </div>
            </div>
            <div class="border-t border-outline-variant/15 px-6 py-4 flex items-center justify-between">
                <div>
                    <div class="font-mono text-[10px] text-on-surface-variant">Prize Pool</div>
                    <div class="font-display font-bold text-primary-container">{{ $ev['prize'] }}</div>
                </div>
                <a href="{{ route('events') }}"
                   class="font-mono text-xs text-primary-container hover:text-primary border border-primary-container/40
                          hover:border-primary-container px-4 py-2 rounded-lg transition-all">
                    Register
                </a>
            </div>
        </div>
        @endforeach
    </div>
</section>

{{-- ══════════════════════════════════════════════
     FEATURES TEASER
     ══════════════════════════════════════════════ --}}
<section class="py-24 border-t border-outline-variant/10" style="background: #0b0f0c;">
    <div class="max-w-7xl mx-auto px-5 sm:px-8">
        <div class="text-center mb-14 anim-on-scroll">
            <p class="font-mono text-xs text-primary-container tracking-widest uppercase mb-3">Built different</p>
            <h2 class="font-display font-black text-4xl md:text-5xl text-on-surface tracking-tight mb-4">Master the Pitch</h2>
            <p class="text-on-surface-variant max-w-lg mx-auto">Everything you need to dominate your campus fantasy league.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            @foreach([
                ['icon' => 'groups', 'title' => 'Squad Builder', 'desc' => 'Draft 11 players with a smart budget system and formation tools.', 'delay' => ''],
                ['icon' => 'bolt', 'title' => 'Live Scoring',  'desc' => 'Real-time fantasy points from actual campus match statistics.', 'delay' => 'anim-delay-1'],
                ['icon' => 'leaderboard', 'title' => 'Leaderboards', 'desc' => 'Global campus, faculty, and private mini-league rankings.', 'delay' => 'anim-delay-2'],
                ['icon' => 'emoji_events', 'title' => 'Real Prizes', 'desc' => 'Win cash prizes, merch, and exclusive campus perks every week.', 'delay' => 'anim-delay-3'],
            ] as $feat)
            <div class="neo-surface rounded-2xl p-7 text-center hover-lift anim-on-scroll {{ $feat['delay'] }}">
                <div class="w-14 h-14 rounded-2xl bg-primary-container/10 border border-primary-container/20
                            flex items-center justify-center mx-auto mb-5">
                    <span class="material-symbols-outlined text-primary-container text-[26px]">{{ $feat['icon'] }}</span>
                </div>
                <h3 class="font-display font-bold text-lg text-on-surface mb-2">{{ $feat['title'] }}</h3>
                <p class="text-on-surface-variant text-sm leading-relaxed">{{ $feat['desc'] }}</p>
            </div>
            @endforeach
        </div>

        <div class="text-center mt-10 anim-on-scroll">
            <a href="{{ route('features') }}"
               class="inline-flex items-center gap-2 font-mono text-sm text-primary-container
                      hover:text-primary tracking-wider uppercase transition-colors">
                All Features
                <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
            </a>
        </div>
    </div>
</section>

{{-- ══════════════════════════════════════════════
     LEADERBOARD PREVIEW
     ══════════════════════════════════════════════ --}}
<section class="max-w-7xl mx-auto px-5 sm:px-8 py-24">
    <div class="flex items-end justify-between mb-12">
        <div class="anim-on-scroll">
            <p class="font-mono text-xs text-secondary-container tracking-widest uppercase mb-2">Top performers</p>
            <h2 class="font-display font-black text-4xl text-on-surface tracking-tight">Campus Leaderboard</h2>
        </div>
        <a href="{{ route('leaderboard') }}"
           class="hidden sm:inline-flex items-center gap-2 font-mono text-xs text-on-surface-variant
                  hover:text-primary-container tracking-wider uppercase transition-colors anim-on-scroll">
            Full Leaderboard
            <span class="material-symbols-outlined text-[14px]">arrow_forward</span>
        </a>
    </div>

    <div class="neo-surface rounded-2xl overflow-hidden anim-on-scroll">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-outline-variant/20" style="background: rgba(0,230,118,0.03);">
                        <th class="font-mono text-[10px] text-on-surface-variant text-left px-6 py-4 tracking-widest uppercase">Rank</th>
                        <th class="font-mono text-[10px] text-on-surface-variant text-left px-4 py-4 tracking-widest uppercase">Player</th>
                        <th class="font-mono text-[10px] text-on-surface-variant text-left px-4 py-4 tracking-widest uppercase hidden sm:table-cell">Faculty</th>
                        <th class="font-mono text-[10px] text-on-surface-variant text-left px-4 py-4 tracking-widest uppercase hidden md:table-cell">Squad</th>
                        <th class="font-mono text-[10px] text-on-surface-variant text-right px-6 py-4 tracking-widest uppercase">Pts</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant/10">
                    @foreach([
                        ['rank' => 1,  'medal' => '🥇', 'name' => 'TundeTheGoat',   'faculty' => 'Engineering',      'squad' => 'Iron Giants FC',   'pts' => 1842, 'trend' => '+'],
                        ['rank' => 2,  'medal' => '🥈', 'name' => 'LexiLawFC',       'faculty' => 'Law',              'squad' => 'Objection United', 'pts' => 1779, 'trend' => '+'],
                        ['rank' => 3,  'medal' => '🥉', 'name' => 'MedBallKing',     'faculty' => 'Medicine',         'squad' => 'Scalpel XI',       'pts' => 1703, 'trend' => '-'],
                        ['rank' => 4,  'medal' => '',   'name' => 'ScienceWizard',   'faculty' => 'Sciences',         'squad' => 'Periodic Eleven',  'pts' => 1688, 'trend' => '+'],
                        ['rank' => 5,  'medal' => '',   'name' => 'FarmKingFC',      'faculty' => 'Agriculture',      'squad' => 'Green Valley XI',  'pts' => 1652, 'trend' => '='],
                    ] as $p)
                    <tr class="table-row-hover transition-colors">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-2">
                                @if($p['medal'])
                                <span class="text-lg">{{ $p['medal'] }}</span>
                                @else
                                <span class="font-mono font-bold text-on-surface-variant text-sm w-[26px] text-center">{{ $p['rank'] }}</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-surface-container-high border border-outline-variant/40
                                            flex items-center justify-center flex-shrink-0">
                                    <span class="font-mono text-[11px] text-on-surface-variant font-bold">
                                        {{ strtoupper(substr($p['name'], 0, 2)) }}
                                    </span>
                                </div>
                                <span class="font-display font-semibold text-sm text-on-surface">{{ $p['name'] }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-4 hidden sm:table-cell">
                            <span class="font-mono text-xs text-on-surface-variant">{{ $p['faculty'] }}</span>
                        </td>
                        <td class="px-4 py-4 hidden md:table-cell">
                            <span class="font-mono text-xs text-on-surface-variant">{{ $p['squad'] }}</span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <span class="font-display font-bold text-primary-container">{{ number_format($p['pts']) }}</span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</section>

{{-- ══════════════════════════════════════════════
     CTA BANNER
     ══════════════════════════════════════════════ --}}
<section class="py-20 border-t border-outline-variant/10 relative overflow-hidden">
    <div class="absolute inset-0 pointer-events-none">
        <div class="absolute inset-0" style="background: linear-gradient(135deg, rgba(0,230,118,0.06) 0%, transparent 60%);"></div>
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[300px] rounded-full"
             style="background: radial-gradient(ellipse, rgba(0,230,118,0.08) 0%, transparent 70%);"></div>
    </div>
    <div class="max-w-3xl mx-auto px-5 sm:px-8 text-center relative anim-on-scroll">
        <div class="badge-live inline-flex mb-6">JOIN 620+ CAMPUS PLAYERS</div>
        <h2 class="font-display font-black text-5xl md:text-6xl text-on-surface tracking-tight mb-6">
            Ready to dominate<br />
            <span class="text-gradient">your campus?</span>
        </h2>
        <p class="text-on-surface-variant text-lg max-w-xl mx-auto mb-10">
            Sign up free, build your dream squad from real campus players, and compete for weekly prizes.
            No credit card. No catch. Just football.
        </p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="{{ route('register') }}"
               id="cta-register-btn"
               class="inline-flex items-center justify-center gap-2 bg-primary-container text-background
                      font-mono font-bold text-sm tracking-wider uppercase px-10 py-4 rounded-xl
                      hover:bg-primary-fixed transition-all glow-green">
                <span class="material-symbols-outlined text-[18px]">add_circle</span>
                Start Playing Free
            </a>
            <a href="{{ route('login') }}"
               class="inline-flex items-center justify-center gap-2 border border-outline-variant/50
                      text-on-surface-variant font-mono font-semibold text-sm tracking-wider uppercase
                      px-10 py-4 rounded-xl hover:border-outline hover:text-on-surface transition-all">
                Already have an account? Login
            </a>
        </div>
    </div>
</section>

@endsection