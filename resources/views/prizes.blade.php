@extends('layouts.app')

@section('title', 'Prizes')
@section('meta_description', 'Discover the rewards and prizes you can win playing PitchIQ fantasy football. Weekly payouts, end-of-season grand rewards, and custom department merchandise.')

@section('content')
<div class="max-w-7xl mx-auto px-5 sm:px-8 py-16">
    {{-- Header --}}
    <div class="text-center max-w-3xl mx-auto mb-16 anim-on-scroll">
        <span class="badge-live mb-4">Rewards & Recognition</span>
        <h1 class="font-display font-black text-4xl sm:text-5xl lg:text-6xl text-on-surface tracking-tight mb-6">
            Win Real Campus <span class="text-gradient-gold">Prizes</span>
        </h1>
        <p class="text-on-surface-variant text-base sm:text-lg leading-relaxed">
            Drafting a squad isn't just about bragging rights. We award real cash prizes and high-end merch to the top managers. Play for free, claim your rewards.
        </p>
    </div>

    {{-- Prize Tiers --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-24 max-w-5xl mx-auto">
        {{-- Match Showdowns --}}
        <div class="glass border-outline-variant/30 hover-lift p-8 rounded-2xl flex flex-col justify-between anim-on-scroll">
            <div>
                <div class="w-12 h-12 rounded-xl bg-surface-container border border-outline-variant/40 flex items-center justify-center mb-6">
                    <span class="material-symbols-outlined text-primary-container text-2xl">sports_soccer</span>
                </div>
                <h3 class="font-display font-bold text-xl text-on-surface mb-2">Match Showdowns</h3>
                <p class="text-on-surface-variant text-sm leading-relaxed mb-6">
                    Compete on a match-by-match basis. The pool collected from token entries (both cash and ad revenue) is distributed directly to the top managers of that match.
                </p>
            </div>
            <div>
                <div class="border-t border-outline-variant/20 pt-6">
                    <span class="font-mono text-xs text-on-surface-variant uppercase tracking-wider block mb-1">Payout Distribution</span>
                    <span class="font-display font-extrabold text-lg text-primary-container block">1st Place: 50% Pool</span>
                    <span class="font-display text-xs text-on-surface-variant block mt-1">2nd Place: 30% · 3rd Place: 20%</span>
                </div>
            </div>
        </div>

        {{-- Featured Derbies --}}
        <div class="glass border-secondary-container/40 glow-gold hover-lift p-8 rounded-2xl flex flex-col justify-between relative anim-on-scroll anim-delay-1">
            <div class="absolute -top-3 right-6 bg-secondary-container text-background font-mono text-[9px] font-black tracking-widest uppercase px-3 py-1 rounded-full shadow-[0_0_12px_rgba(253,212,0,0.4)]">
                Boosted
            </div>
            <div>
                <div class="w-12 h-12 rounded-xl bg-secondary-container/10 border border-secondary-container/30 flex items-center justify-center mb-6">
                    <span class="material-symbols-outlined text-secondary-container text-2xl">emoji_events</span>
                </div>
                <h3 class="font-display font-bold text-xl text-on-surface mb-2">Featured Derbies</h3>
                <p class="text-on-surface-variant text-sm leading-relaxed mb-6">
                    High-profile departmental matchups and level finals receive sponsor boosts, guaranteeing a large cash payout regardless of entry size.
                </p>
            </div>
            <div>
                <div class="border-t border-outline-variant/20 pt-6">
                    <span class="font-mono text-xs text-on-surface-variant uppercase tracking-wider block mb-1">Guaranteed Rewards</span>
                    <span class="font-display font-black text-2xl text-secondary-container">Boosted Pools</span>
                    <p class="text-[10px] text-on-surface-variant mt-1">+ Exclusive Custom Merch</p>
                </div>
            </div>
        </div>

        {{-- Daily Ad Tokens --}}
        <div class="glass border-outline-variant/30 hover-lift p-8 rounded-2xl flex flex-col justify-between anim-on-scroll anim-delay-2">
            <div>
                <div class="w-12 h-12 rounded-xl bg-surface-container border border-outline-variant/40 flex items-center justify-center mb-6">
                    <span class="material-symbols-outlined text-on-surface-variant text-2xl">toll</span>
                </div>
                <h3 class="font-display font-bold text-xl text-on-surface mb-2">Play Free with Ads</h3>
                <p class="text-on-surface-variant text-sm leading-relaxed mb-6">
                    Earn tokens directly by watching short video ads. Accumulate tokens to enter matches for free without spending your own cash.
                </p>
            </div>
            <div>
                <div class="border-t border-outline-variant/20 pt-6">
                    <span class="font-mono text-xs text-on-surface-variant uppercase tracking-wider block mb-1">Ad Rewards</span>
                    <span class="font-display font-extrabold text-2xl text-primary-container">5 Tokens / Ad</span>
                    <p class="text-[10px] text-on-surface-variant mt-1">Limit: 3 Ads (15 Tokens) Per Day</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Rules & Qualifications --}}
    <div class="mt-24 max-w-4xl mx-auto anim-on-scroll">
        <h2 class="font-display font-bold text-3xl text-on-surface text-center mb-12">
            How to <span class="text-gradient">Qualify</span>
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div class="glass p-6 sm:p-8 rounded-2xl">
                <h4 class="font-display font-bold text-lg text-on-surface mb-3 flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary-container text-lg">check_circle</span>
                    Fair Play Rules
                </h4>
                <p class="text-on-surface-variant text-sm leading-relaxed">
                    Managers are limited to one squad per person. Creating multiple accounts to game the system is strictly prohibited. Our system monitors IP addresses and user verification details.
                </p>
            </div>

            <div class="glass p-6 sm:p-8 rounded-2xl">
                <h4 class="font-display font-bold text-lg text-on-surface mb-3 flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary-container text-lg">check_circle</span>
                    Eligibility
                </h4>
                <p class="text-on-surface-variant text-sm leading-relaxed">
                    You must be a registered, verified manager to claim the rewards and merchandise. Verify your account in your profile or settings tab.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
