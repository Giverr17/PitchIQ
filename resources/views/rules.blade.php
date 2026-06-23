@extends('layouts.app')

@section('title', 'Game Rules')
@section('meta_description', 'The rules of PitchIQ — tokens, squad sizes, budgets, draft deadlines, captaincy and fair play.')

@section('content')
<div class="max-w-4xl mx-auto px-5 sm:px-8 py-16">

    {{-- Header --}}
    <div class="text-center max-w-3xl mx-auto mb-14 anim-on-scroll">
        <span class="badge-live mb-4">Rules</span>
        <h1 class="font-display font-black text-4xl sm:text-5xl text-on-surface tracking-tight mb-6">
            Game <span class="text-gradient">Rules</span>
        </h1>
        <p class="text-on-surface-variant text-base sm:text-lg leading-relaxed">
            Everything you need to play fair and compete for the top of the leaderboard.
        </p>
    </div>

    @php
        $rules = [
            ['icon' => 'toll', 'title' => 'Tokens', 'body' => 'You need tokens to take part. New accounts start with 20 free tokens (40 if a friend invited you), plus a +10 daily login bonus. Predictions and squad entries cost 5 tokens each.'],
            ['icon' => 'groups', 'title' => 'Squad Size', 'body' => 'Depending on the tournament, you draft either a 5-a-side or an 11-a-side squad — using only players from the teams competing in that fixture.'],
            ['icon' => 'savings', 'title' => 'Budget', 'body' => 'Each squad has a fantasy coin budget set per squad size. Every player has a price, and your selections must stay within budget, alongside a limit on how many players you can take from one team.'],
            ['icon' => 'military_tech', 'title' => 'Captaincy', 'body' => 'Nominate one captain per squad. Your captain earns double points for that match, so choose the player you trust most.'],
            ['icon' => 'lock_clock', 'title' => 'Deadlines', 'body' => 'Squads and predictions lock before kick-off. Once a fixture starts you can no longer edit your entry for it.'],
            ['icon' => 'scoreboard', 'title' => 'Scoring', 'body' => 'Points are earned from real match events — goals, assists, clean sheets, saves, appearances and more — and applied automatically when results are confirmed. See the full Scoring System for the exact values.'],
            ['icon' => 'redeem', 'title' => 'Prizes', 'body' => 'Top managers are rewarded per tournament. Prizes vary by competition and can include airtime, cash, or other perks set by the organisers.'],
            ['icon' => 'gavel', 'title' => 'Fair Play', 'body' => 'One account per person. Abuse, multiple accounts, or attempts to manipulate results may lead to disqualification and forfeiture of tokens or prizes.'],
        ];
    @endphp

    <div class="space-y-4">
        @foreach($rules as $rule)
            <div class="glass rounded-2xl p-6 flex gap-4 anim-on-scroll">
                <div class="w-10 h-10 rounded-xl bg-primary-container/10 border border-primary-container/25 flex items-center justify-center flex-shrink-0">
                    <span class="material-symbols-outlined text-primary-container text-[20px]">{{ $rule['icon'] }}</span>
                </div>
                <div>
                    <h3 class="font-display font-bold text-lg text-on-surface mb-1">{{ $rule['title'] }}</h3>
                    <p class="text-on-surface-variant text-sm leading-relaxed">{{ $rule['body'] }}</p>
                </div>
            </div>
        @endforeach
    </div>

    <div class="flex flex-wrap justify-center gap-4 mt-12 anim-on-scroll">
        <a href="{{ route('scoring') }}" class="inline-flex items-center gap-2 text-sm font-mono text-primary-container hover:underline">
            <span class="material-symbols-outlined text-[16px]">scoreboard</span> Scoring System
        </a>
        <a href="{{ route('how-it-works') }}" class="inline-flex items-center gap-2 text-sm font-mono text-primary-container hover:underline">
            <span class="material-symbols-outlined text-[16px]">help</span> How It Works
        </a>
    </div>
</div>
@endsection

@push('ads')
    @include('partials.propeller-ad')
@endpush
