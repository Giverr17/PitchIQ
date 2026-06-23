@extends('layouts.app')

@section('title', 'Scoring System')
@section('meta_description', 'How PitchIQ fantasy points are calculated — goals, assists, clean sheets, appearances, saves, cards and the captain bonus.')

@section('content')
<div class="max-w-4xl mx-auto px-5 sm:px-8 py-16">

    {{-- Header --}}
    <div class="text-center max-w-3xl mx-auto mb-14 anim-on-scroll">
        <span class="badge-live mb-4">Scoring</span>
        <h1 class="font-display font-black text-4xl sm:text-5xl text-on-surface tracking-tight mb-6">
            How Points <span class="text-gradient">Are Scored</span>
        </h1>
        <p class="text-on-surface-variant text-base sm:text-lg leading-relaxed">
            Your players earn fantasy points from what they actually do in their match. Here's the exact breakdown.
        </p>
    </div>

    @php
        $scoring = [
            ['action' => 'Goal (GK / DEF)',        'pts' => '+6'],
            ['action' => 'Goal (MID)',             'pts' => '+5'],
            ['action' => 'Goal (FWD)',             'pts' => '+4'],
            ['action' => 'Assist',                 'pts' => '+3'],
            ['action' => 'Clean sheet (GK / DEF, 60+ mins)', 'pts' => '+4'],
            ['action' => 'Clean sheet (MID, 60+ mins)',      'pts' => '+1'],
            ['action' => 'Playing 60–89 minutes',  'pts' => '+1'],
            ['action' => 'Playing 90 minutes',     'pts' => '+2'],
            ['action' => 'Every 3 saves (GK)',     'pts' => '+1'],
            ['action' => 'Penalty saved',          'pts' => '+5'],
            ['action' => 'Yellow card',            'pts' => '−1'],
            ['action' => 'Own goal',               'pts' => '−2'],
            ['action' => 'Penalty miss',           'pts' => '−2'],
            ['action' => 'Red card',               'pts' => '−3'],
        ];
    @endphp

    {{-- Scoring table --}}
    <div class="glass rounded-2xl overflow-hidden anim-on-scroll mb-10">
        <table class="w-full text-left">
            <thead>
                <tr class="border-b border-outline-variant/15">
                    <th class="py-4 px-6 font-mono text-[11px] font-bold uppercase tracking-wider text-on-surface-variant">Action</th>
                    <th class="py-4 px-6 font-mono text-[11px] font-bold uppercase tracking-wider text-on-surface-variant text-right">Points</th>
                </tr>
            </thead>
            <tbody>
                @foreach($scoring as $row)
                    <tr class="border-b border-outline-variant/10 last:border-0">
                        <td class="py-3.5 px-6 text-sm text-on-surface">{{ $row['action'] }}</td>
                        <td class="py-3.5 px-6 text-right font-mono font-bold text-sm {{ str_starts_with($row['pts'], '−') ? 'text-error' : 'text-primary-container' }}">
                            {{ $row['pts'] }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Extras --}}
    <div class="grid sm:grid-cols-2 gap-5">
        <div class="glass rounded-2xl p-6 anim-on-scroll">
            <div class="flex items-center gap-2 mb-2">
                <span class="material-symbols-outlined text-primary-container text-[20px]">military_tech</span>
                <h3 class="font-display font-bold text-lg text-on-surface">Captain Bonus</h3>
            </div>
            <p class="text-on-surface-variant text-sm leading-relaxed">
                Your captain's total points for the match are <strong class="text-secondary-container">doubled</strong>.
                Pick wisely — a big captain haul can win you the matchday.
            </p>
        </div>
        <div class="glass rounded-2xl p-6 anim-on-scroll anim-delay-1">
            <div class="flex items-center gap-2 mb-2">
                <span class="material-symbols-outlined text-primary-container text-[20px]">add_circle</span>
                <h3 class="font-display font-bold text-lg text-on-surface">Bonus Points</h3>
            </div>
            <p class="text-on-surface-variant text-sm leading-relaxed">
                Standout performers may receive extra <strong class="text-secondary-container">bonus points</strong>
                awarded by the match organisers when results are confirmed.
            </p>
        </div>
    </div>

    <p class="text-center font-mono text-[11px] text-on-surface-variant/50 mt-10">
        Points are applied automatically once a match result is finalised.
    </p>

    <div class="text-center mt-10 anim-on-scroll">
        <a href="{{ route('how-it-works') }}" class="inline-flex items-center gap-2 text-sm font-mono text-primary-container hover:underline">
            <span class="material-symbols-outlined text-[16px]">arrow_back</span> Back to How It Works
        </a>
    </div>
</div>
@endsection

@push('ads')
    @include('partials.propeller-ad')
@endpush
