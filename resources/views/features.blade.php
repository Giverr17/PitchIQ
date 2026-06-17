@extends('layouts.app')

@section('title', 'Features')
@section('meta_description', 'Explore the unique features of PitchIQ: Token entry system, per-game drafts, inter-departmental matches, real-time stats tracking, and live game updates.')

@section('content')
<div class="max-w-7xl mx-auto px-5 sm:px-8 py-16">
    {{-- Header --}}
    <div class="text-center max-w-3xl mx-auto mb-16 anim-on-scroll">
        <span class="badge-live mb-4">Platform Features</span>
        <h1 class="font-display font-black text-4xl sm:text-5xl lg:text-6xl text-on-surface tracking-tight mb-6">
            Everything You Need to <span class="text-gradient">Win.</span>
        </h1>
        <p class="text-on-surface-variant text-base sm:text-lg leading-relaxed">
            From quick token entries to match-by-match fresh drafts, PitchIQ is optimized for departmental and level-based fantasy matchups.
        </p>
    </div>

    {{-- Feature Grid (6 Cards) --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mb-24">
        {{-- Card 1 --}}
        <div class="glass hover-lift p-8 rounded-2xl anim-on-scroll">
            <div class="w-12 h-12 rounded-xl bg-primary-container/10 border border-primary-container/30 flex items-center justify-center mb-6">
                <span class="material-symbols-outlined text-primary-container text-2xl">toll</span>
            </div>
            <h3 class="font-display font-bold text-xl text-on-surface mb-3">Token Entry Economy</h3>
            <p class="text-on-surface-variant text-sm leading-relaxed">
                Enter matches using 10-50 tokens. Watch quick video ads to claim tokens for free, or purchase packs directly to jump straight into the action.
            </p>
        </div>

        {{-- Card 2 --}}
        <div class="glass hover-lift p-8 rounded-2xl anim-on-scroll anim-delay-1">
            <div class="w-12 h-12 rounded-xl bg-primary-container/10 border border-primary-container/30 flex items-center justify-center mb-6">
                <span class="material-symbols-outlined text-primary-container text-2xl">autorenew</span>
            </div>
            <h3 class="font-display font-bold text-xl text-on-surface mb-3">Fresh Roster Per Match</h3>
            <p class="text-on-surface-variant text-sm leading-relaxed">
                Forget long seasonal commitments and weekly transfer limits. Draft a brand new squad of 11 players for every match you join.
            </p>
        </div>

        {{-- Card 3 --}}
        <div class="glass hover-lift p-8 rounded-2xl anim-on-scroll anim-delay-2">
            <div class="w-12 h-12 rounded-xl bg-primary-container/10 border border-primary-container/30 flex items-center justify-center mb-6">
                <span class="material-symbols-outlined text-primary-container text-2xl">sports_soccer</span>
            </div>
            <h3 class="font-display font-bold text-xl text-on-surface mb-3">Inter-Level Department Matchups</h3>
            <p class="text-on-surface-variant text-sm leading-relaxed">
                Compete on localized departmental matches (e.g. 100 level vs 300 level) and climb custom community match boards.
            </p>
        </div>

        {{-- Card 4 --}}
        <div class="glass hover-lift p-8 rounded-2xl anim-on-scroll anim-delay-3">
            <div class="w-12 h-12 rounded-xl bg-primary-container/10 border border-primary-container/30 flex items-center justify-center mb-6">
                <span class="material-symbols-outlined text-primary-container text-2xl">insights</span>
            </div>
            <h3 class="font-display font-bold text-xl text-on-surface mb-3">Live Performance Stats</h3>
            <p class="text-on-surface-variant text-sm leading-relaxed">
                Vetted match statistics, including goals, defensive blocks, and key passes, update in real-time as the pitch action unfolds.
            </p>
        </div>

        {{-- Card 5 --}}
        <div class="glass hover-lift p-8 rounded-2xl anim-on-scroll anim-delay-4">
            <div class="w-12 h-12 rounded-xl bg-primary-container/10 border border-primary-container/30 flex items-center justify-center mb-6">
                <span class="material-symbols-outlined text-primary-container text-2xl">videocam</span>
            </div>
            <h3 class="font-display font-bold text-xl text-on-surface mb-3">Watch Ads to Earn</h3>
            <p class="text-on-surface-variant text-sm leading-relaxed">
                Don't want to spend cash? Simply watch brief ads to accumulate tokens. Every ad watched awards tokens to fund your next entry.
            </p>
        </div>

        {{-- Card 6 --}}
        <div class="glass hover-lift p-8 rounded-2xl anim-on-scroll anim-delay-5">
            <div class="w-12 h-12 rounded-xl bg-primary-container/10 border border-primary-container/30 flex items-center justify-center mb-6">
                <span class="material-symbols-outlined text-primary-container text-2xl">notifications_active</span>
            </div>
            <h3 class="font-display font-bold text-xl text-on-surface mb-3">Matchday Alerts</h3>
            <p class="text-on-surface-variant text-sm leading-relaxed">
                Receive push notifications when match drafts open, when countdown deadlines approach, and when match points settle.
            </p>
        </div>
    </div>

    {{-- Comparison Section --}}
    <div class="mt-24 max-w-5xl mx-auto anim-on-scroll">
        <h2 class="font-display font-bold text-3xl sm:text-4xl text-on-surface text-center mb-12">
            Why Choose <span class="text-gradient">PitchIQ?</span>
        </h2>

        <div class="glass rounded-2xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-outline-variant/30 bg-surface-container/30 font-mono text-xs uppercase tracking-wider text-on-surface-variant">
                            <th class="py-5 px-6 font-semibold">Feature / Experience</th>
                            <th class="py-5 px-6 font-semibold text-primary-container">PitchIQ</th>
                            <th class="py-5 px-6 font-semibold">Traditional Fantasy</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/10 text-sm">
                        <tr class="table-row-hover transition-colors">
                            <td class="py-5 px-6 font-semibold text-on-surface">Commitment Level</td>
                            <td class="py-5 px-6 text-primary-container font-medium">Flexible per-game token entries (10-50 tokens)</td>
                            <td class="py-5 px-6 text-on-surface-variant">Rigid season-long weekly management</td>
                        </tr>
                        <tr class="table-row-hover transition-colors">
                            <td class="py-5 px-6 font-semibold text-on-surface">Monetization Options</td>
                            <td class="py-5 px-6 text-primary-container font-medium">Earn tokens by watching ads or purchase packs</td>
                            <td class="py-5 px-6 text-on-surface-variant">Subscription-based or paywalled tools</td>
                        </tr>
                        <tr class="table-row-hover transition-colors">
                            <td class="py-5 px-6 font-semibold text-on-surface">Roster Customization</td>
                            <td class="py-5 px-6 text-primary-container font-medium">Fresh draft for every single match you join</td>
                            <td class="py-5 px-6 text-on-surface-variant">Strict seasonal budgets and trade limits</td>
                        </tr>
                        <tr class="table-row-hover transition-colors">
                            <td class="py-5 px-6 font-semibold text-on-surface">Game Coverage</td>
                            <td class="py-5 px-6 text-primary-container font-medium">Local department, level, and faculty games</td>
                            <td class="py-5 px-6 text-on-surface-variant">International leagues only</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
