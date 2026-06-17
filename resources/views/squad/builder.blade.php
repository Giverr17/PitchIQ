@extends('layouts.app')

@section('title', 'Match Draft')
@section('meta_description', 'Draft your PitchIQ fantasy squad for this departmental match. Select 11 players within a ₦100M virtual budget with player limits.')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-8 py-6 sm:py-12">
    
    {{-- Header / Budget & Entry Details --}}
    <div class="glass p-5 xs:p-6 sm:p-8 rounded-3xl mb-6 sm:mb-10 flex flex-col md:flex-row items-center justify-between gap-6">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="badge-upcoming text-[9px] font-mono tracking-wider px-2 py-0.5">CSC vs MECH (Level 300 Clash)</span>
                <span class="font-mono text-[10px] text-on-surface-variant">Fee: 20 Tokens</span>
            </div>
            <h1 class="font-display font-black text-2xl sm:text-3xl text-on-surface">
                Draft Match <span class="text-gradient">Lineup</span>
            </h1>
            <p class="text-on-surface-variant text-xs mt-1">Select 11 players. Max 6 from a single department/level team.</p>
        </div>
        <div class="flex items-center gap-4 xs:gap-6 sm:gap-8 w-full md:w-auto justify-between md:justify-end flex-wrap xs:flex-nowrap">
            <div class="text-right">
                <span class="block text-xl sm:text-2xl font-black font-mono text-secondary-container">₦45.0M</span>
                <span class="text-[8px] sm:text-[9px] text-on-surface-variant uppercase font-mono tracking-wider">Remaining Budget</span>
            </div>
            <div class="w-[1px] h-8 bg-outline-variant/30"></div>
            <div class="text-right">
                <span class="block text-xl sm:text-2xl font-black font-mono text-primary-container">7 / 11</span>
                <span class="text-[8px] sm:text-[9px] text-on-surface-variant uppercase font-mono tracking-wider">Players Picked</span>
            </div>
            <div class="w-[1px] h-8 bg-outline-variant/30"></div>
            <div class="w-full xs:w-auto">
                <a href="{{ route('dashboard') }}"
                   onclick="alert('Draft successfully locked and entry token fee deducted!')"
                   class="inline-block w-full xs:w-auto text-center bg-primary-container text-background font-mono text-xs font-bold tracking-wider uppercase px-4 sm:px-5 py-3 rounded-xl hover:bg-primary-fixed transition-colors glow-green">
                    Lock Entry
                </a>
            </div>
        </div>
    </div>

    {{-- Builder Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        
        {{-- Pitch Section (7 cols) --}}
        <div class="lg:col-span-7 glass p-6 sm:p-8 rounded-2xl">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
                <h3 class="font-display font-bold text-lg text-on-surface">Match Formation</h3>
                <div class="flex gap-1.5 overflow-x-auto scrollbar-none" data-filter-group="formation">
                    <button class="px-3 py-1.5 rounded-lg border border-outline-variant/30 text-xs font-mono text-on-surface hover:border-primary-container transition-colors flex-shrink-0" data-formation="4-4-2">4-4-2</button>
                    <button class="px-3 py-1.5 rounded-lg border border-outline-variant/30 text-xs font-mono text-on-surface hover:border-primary-container transition-colors bg-primary-container text-background flex-shrink-0" data-formation="3-5-2">3-5-2</button>
                    <button class="px-3 py-1.5 rounded-lg border border-outline-variant/30 text-xs font-mono text-on-surface hover:border-primary-container transition-colors flex-shrink-0" data-formation="4-3-3">4-3-3</button>
                </div>
            </div>

            {{-- Pitch Pattern Layout --}}
            <div class="pitch-pattern border border-outline-variant/30 rounded-2xl p-4 sm:p-6 relative flex flex-col justify-between h-[480px] sm:h-[540px] lg:h-[580px] overflow-hidden">
                <div class="absolute inset-0 bg-gradient-to-t from-background/90 via-transparent to-background/90 pointer-events-none"></div>

                {{-- GK --}}
                <div class="flex justify-center z-10">
                    <button class="text-center group focus:outline-none max-w-[65px] min-[375px]:max-w-[80px]">
                        <div class="w-9 h-9 min-[375px]:w-10 min-[375px]:h-10 sm:w-12 sm:h-12 rounded-full bg-secondary-container border border-outline/50 flex items-center justify-center font-bold text-background text-xs shadow-md mx-auto group-hover:scale-105 transition-transform">GK</div>
                        <span class="block text-[9px] sm:text-[11px] font-bold text-on-surface mt-1 truncate max-w-[55px] min-[375px]:max-w-[70px] sm:max-w-none">Alabi (CSC)</span>
                        <span class="font-mono text-[8px] sm:text-[9px] text-secondary-container">₦5.5M</span>
                    </button>
                </div>

                {{-- DFs --}}
                <div class="flex justify-around z-10 gap-1 sm:gap-2">
                    <button class="text-center group focus:outline-none max-w-[55px] min-[375px]:max-w-[70px] sm:max-w-[90px]">
                        <div class="w-7 h-7 min-[375px]:w-8 min-[375px]:h-8 sm:w-10 sm:h-10 rounded-full bg-surface-container border border-primary-container/40 flex items-center justify-center font-bold text-primary-container text-xs shadow mx-auto group-hover:scale-105 transition-transform">DF</div>
                        <span class="block text-[9px] sm:text-[10px] font-semibold text-on-surface mt-1 truncate max-w-[45px] min-[375px]:max-w-[60px] sm:max-w-none">Okon (MECH)</span>
                        <span class="font-mono text-[8px] sm:text-[9px] text-on-surface-variant">₦6.0M</span>
                    </button>
                    <button class="text-center group focus:outline-none max-w-[55px] min-[375px]:max-w-[70px] sm:max-w-[90px]">
                        <div class="w-7 h-7 min-[375px]:w-8 min-[375px]:h-8 sm:w-10 sm:h-10 rounded-full bg-surface-container border border-primary-container/40 flex items-center justify-center font-bold text-primary-container text-xs shadow mx-auto group-hover:scale-105 transition-transform">DF</div>
                        <span class="block text-[9px] sm:text-[10px] font-semibold text-on-surface mt-1 truncate max-w-[45px] min-[375px]:max-w-[60px] sm:max-w-none">Uche (CSC)</span>
                        <span class="font-mono text-[8px] sm:text-[9px] text-on-surface-variant">₦5.0M</span>
                    </button>
                    <button class="text-center group focus:outline-none max-w-[55px] min-[375px]:max-w-[70px] sm:max-w-[90px]">
                        <div class="w-7 h-7 min-[375px]:w-8 min-[375px]:h-8 sm:w-10 sm:h-10 rounded-full bg-surface-container border border-dashed border-outline-variant/60 flex items-center justify-center font-bold text-on-surface-variant/40 text-xs shadow mx-auto group-hover:border-primary-container transition-colors">+</div>
                        <span class="block text-[9px] sm:text-[10px] font-semibold text-on-surface-variant mt-1 truncate max-w-[45px] min-[375px]:max-w-[60px] sm:max-w-none">Add Defender</span>
                        <span class="font-mono text-[8px] sm:text-[9px] text-on-surface-variant/40">-</span>
                    </button>
                </div>

                {{-- MFs --}}
                <div class="flex justify-around z-10 gap-1 sm:gap-2">
                    <button class="text-center group focus:outline-none max-w-[50px] min-[375px]:max-w-[64px] sm:max-w-[80px]">
                        <div class="w-7 h-7 min-[375px]:w-8 min-[375px]:h-8 sm:w-10 sm:h-10 rounded-full bg-surface-container border border-primary-container/40 flex items-center justify-center font-bold text-primary-container text-xs shadow mx-auto group-hover:scale-105 transition-transform">MF</div>
                        <span class="block text-[9px] sm:text-[10px] font-semibold text-on-surface mt-1 truncate max-w-[40px] min-[375px]:max-w-[55px] sm:max-w-none">Kalu (MECH)</span>
                        <span class="font-mono text-[8px] sm:text-[9px] text-on-surface-variant">₦7.0M</span>
                    </button>
                    <button class="text-center group focus:outline-none max-w-[50px] min-[375px]:max-w-[64px] sm:max-w-[80px]">
                        <div class="w-7 h-7 min-[375px]:w-8 min-[375px]:h-8 sm:w-10 sm:h-10 rounded-full bg-surface-container border border-primary-container/40 flex items-center justify-center font-bold text-primary-container text-xs shadow mx-auto group-hover:scale-105 transition-transform">MF</div>
                        <span class="block text-[9px] sm:text-[10px] font-semibold text-on-surface mt-1 truncate max-w-[40px] min-[375px]:max-w-[55px] sm:max-w-none">Sani (CSC)</span>
                        <span class="font-mono text-[8px] sm:text-[9px] text-on-surface-variant">₦6.5M</span>
                    </button>
                    <button class="text-center group focus:outline-none max-w-[50px] min-[375px]:max-w-[64px] sm:max-w-[80px]">
                        <div class="w-7 h-7 min-[375px]:w-8 min-[375px]:h-8 sm:w-10 sm:h-10 rounded-full bg-surface-container border border-dashed border-outline-variant/60 flex items-center justify-center font-bold text-on-surface-variant/40 text-xs shadow mx-auto group-hover:border-primary-container transition-colors">+</div>
                        <span class="block text-[9px] sm:text-[10px] font-semibold text-on-surface-variant mt-1 truncate max-w-[40px] min-[375px]:max-w-[55px] sm:max-w-none">Add Midfield</span>
                        <span class="font-mono text-[8px] sm:text-[9px] text-on-surface-variant/40">-</span>
                    </button>
                    <button class="text-center group focus:outline-none max-w-[50px] min-[375px]:max-w-[64px] sm:max-w-[80px]">
                        <div class="w-7 h-7 min-[375px]:w-8 min-[375px]:h-8 sm:w-10 sm:h-10 rounded-full bg-surface-container border border-dashed border-outline-variant/60 flex items-center justify-center font-bold text-on-surface-variant/40 text-xs shadow mx-auto group-hover:border-primary-container transition-colors">+</div>
                        <span class="block text-[9px] sm:text-[10px] font-semibold text-on-surface-variant mt-1 truncate max-w-[40px] min-[375px]:max-w-[55px] sm:max-w-none">Add Midfield</span>
                        <span class="font-mono text-[8px] sm:text-[9px] text-on-surface-variant/40">-</span>
                    </button>
                </div>

                {{-- FWs --}}
                <div class="flex justify-around z-10 gap-1 sm:gap-2">
                    <button class="text-center group focus:outline-none max-w-[55px] min-[375px]:max-w-[70px] sm:max-w-[90px]">
                        <div class="w-9 h-9 min-[375px]:w-10 min-[375px]:h-10 sm:w-12 sm:h-12 rounded-full bg-primary-container/20 border border-primary-container flex items-center justify-center font-bold text-primary-container text-xs shadow-lg mx-auto group-hover:scale-105 transition-transform">FW</div>
                        <span class="block text-[9px] sm:text-xs font-bold text-on-surface mt-1 truncate max-w-[45px] min-[375px]:max-w-[60px] sm:max-w-none">Victor (MECH)</span>
                        <span class="font-mono text-[8px] sm:text-[10px] text-primary-container font-semibold">₦9.5M</span>
                    </button>
                    <button class="text-center group focus:outline-none max-w-[55px] min-[375px]:max-w-[70px] sm:max-w-[90px]">
                        <div class="w-7 h-7 min-[375px]:w-8 min-[375px]:h-8 sm:w-10 sm:h-10 rounded-full bg-surface-container border border-dashed border-outline-variant/60 flex items-center justify-center font-bold text-on-surface-variant/40 text-xs shadow mx-auto group-hover:border-primary-container transition-colors">+</div>
                        <span class="block text-[9px] sm:text-[10px] font-semibold text-on-surface-variant mt-1 truncate max-w-[45px] min-[375px]:max-w-[60px] sm:max-w-none">Add Forward</span>
                        <span class="font-mono text-[8px] sm:text-[9px] text-on-surface-variant/40">-</span>
                    </button>
                </div>

            </div>
        </div>

        {{-- Selection Panel Section (5 cols) --}}
        <div class="lg:col-span-5 glass p-6 rounded-2xl flex flex-col h-[500px] sm:h-[550px] lg:h-[580px]">
            
            {{-- Tab Selectors --}}
            <div class="flex flex-col xs:flex-row xs:items-center justify-between border-b border-outline-variant/20 pb-4 mb-4 gap-3">
                <h3 class="font-display font-bold text-base text-on-surface">Available Players</h3>
                <div class="flex gap-1 overflow-x-auto scrollbar-none pb-1 xs:pb-0" data-filter-group="players-pos">
                    <button class="px-2.5 py-1 rounded bg-primary-container text-background font-mono text-[10px] font-bold flex-shrink-0" data-filter="all">ALL</button>
                    <button class="px-2.5 py-1 rounded border border-outline-variant/30 text-on-surface-variant font-mono text-[10px] hover:border-primary-container hover:text-on-surface flex-shrink-0" data-filter="gk">GK</button>
                    <button class="px-2.5 py-1 rounded border border-outline-variant/30 text-on-surface-variant font-mono text-[10px] hover:border-primary-container hover:text-on-surface flex-shrink-0" data-filter="df">DF</button>
                    <button class="px-2.5 py-1 rounded border border-outline-variant/30 text-on-surface-variant font-mono text-[10px] hover:border-primary-container hover:text-on-surface flex-shrink-0" data-filter="mf">MF</button>
                    <button class="px-2.5 py-1 rounded border border-outline-variant/30 text-on-surface-variant font-mono text-[10px] hover:border-primary-container hover:text-on-surface flex-shrink-0" data-filter="fw">FW</button>
                </div>
            </div>

            {{-- Match Filters Info --}}
            <div class="p-3 bg-surface-container/30 border border-outline-variant/20 rounded-xl mb-4 text-[10px] text-on-surface-variant leading-relaxed">
                Filter shows players from <strong>CSC</strong> and <strong>MECH</strong> teams only for this match draft.
            </div>

            {{-- Players List Container --}}
            <div class="flex-1 overflow-y-auto space-y-2 pr-1">
                @php
                    $roster = [
                        ['name' => 'Emeka Okafor', 'fac' => 'CSC', 'pos' => 'FW', 'val' => '₦10.5M', 'pts' => 92, 'class' => 'fw'],
                        ['name' => 'Babatunde Raji', 'fac' => 'MECH', 'pos' => 'MF', 'val' => '₦8.0M', 'pts' => 74, 'class' => 'mf'],
                        ['name' => 'Yusuf Abubakar', 'fac' => 'MECH', 'pos' => 'DF', 'val' => '₦5.5M', 'pts' => 61, 'class' => 'df'],
                        ['name' => 'Chinedu Eze', 'fac' => 'CSC', 'pos' => 'GK', 'val' => '₦6.0M', 'pts' => 54, 'class' => 'gk'],
                        ['name' => 'Tosan Wey', 'fac' => 'CSC', 'pos' => 'MF', 'val' => '₦7.5M', 'pts' => 68, 'class' => 'mf'],
                        ['name' => 'David Adeleke', 'fac' => 'MECH', 'pos' => 'FW', 'val' => '₦9.0M', 'pts' => 83, 'class' => 'fw'],
                        ['name' => 'Adebayo Alao', 'fac' => 'MECH', 'pos' => 'DF', 'val' => '₦4.5M', 'pts' => 42, 'class' => 'df'],
                    ];
                @endphp

                @foreach($roster as $p)
                    <div class="flex items-center justify-between p-2.5 sm:p-3 rounded-xl border border-outline-variant/20 bg-surface-container/10 hover:border-primary-container/40 transition-colors"
                         data-filter-target="players-pos" data-filter-value="{{ $p['class'] }}">
                        <div class="flex items-center min-w-0 flex-1 mr-2">
                            <span class="font-mono text-[9px] font-black uppercase px-1.5 py-0.5 rounded mr-2 flex-shrink-0 {{ $p['pos'] === 'GK' ? 'bg-secondary-container text-background' : 'bg-primary-container/15 text-primary-container' }}">
                                {{ $p['pos'] }}
                            </span>
                            <div class="min-w-0">
                                <span class="font-display font-bold text-xs sm:text-sm text-on-surface truncate block" title="{{ $p['name'] }}">{{ $p['name'] }}</span>
                                <span class="block text-[10px] text-on-surface-variant mt-0.5">{{ $p['fac'] }} Team</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2.5 sm:gap-4 flex-shrink-0">
                            <div class="text-right">
                                <span class="block font-mono text-xs font-bold text-on-surface">{{ $p['val'] }}</span>
                                <span class="block text-[9px] text-on-surface-variant font-mono">{{ $p['pts'] }} pts</span>
                            </div>
                            <button class="w-8 h-8 rounded-lg bg-primary-container/10 text-primary-container border border-primary-container/20 flex items-center justify-center hover:bg-primary-container hover:text-background transition-colors font-bold flex-shrink-0">
                                +
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>

        </div>

    </div>

</div>
@endsection
