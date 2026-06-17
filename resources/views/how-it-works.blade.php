@extends('layouts.app')

@section('title', 'How It Works')
@section('meta_description', 'Learn how to play PitchIQ fantasy football. Get entry tokens, select departmental matches, draft a fresh team, and win match prizes.')

@section('content')
<div class="max-w-7xl mx-auto px-5 sm:px-8 py-16">
    {{-- Header --}}
    <div class="text-center max-w-3xl mx-auto mb-16 anim-on-scroll">
        <span class="badge-live mb-4">How To Play</span>
        <h1 class="font-display font-black text-4xl sm:text-5xl lg:text-6xl text-on-surface tracking-tight mb-6">
            Tokens. Draft. <span class="text-gradient">Win.</span>
        </h1>
        <p class="text-on-surface-variant text-base sm:text-lg leading-relaxed">
            PitchIQ lets you compete on a match-by-match basis. Earn tokens by watching ads or buy them directly to draft a fresh squad for any departmental or inter-level match.
        </p>
    </div>

    {{-- 5-Step Timeline --}}
    <div class="relative border-l border-outline-variant/30 ml-4 sm:ml-8 md:mx-auto md:max-w-4xl space-y-12 pb-16">
        
        {{-- Step 1 --}}
        <div class="relative pl-8 sm:pl-12 anim-on-scroll">
            <div class="absolute -left-[17px] top-0 w-8 h-8 rounded-full bg-surface-container border border-primary-container flex items-center justify-center font-mono text-xs font-bold text-primary-container shadow-[0_0_12px_rgba(0,230,118,0.3)]">
                1
            </div>
            <div class="glass hover-lift p-6 sm:p-8 rounded-2xl">
                <span class="font-mono text-xs font-semibold tracking-wider text-primary-container uppercase mb-2 block">Step One</span>
                <h3 class="font-display font-bold text-xl sm:text-2xl text-on-surface mb-3">Acquire Tokens</h3>
                <p class="text-on-surface-variant text-sm sm:text-base leading-relaxed">
                    Matches cost between <strong class="text-secondary-container">10-50 tokens</strong> to enter. Earn 5 tokens per video ad view (limit of 3 ads/15 tokens per day), or buy token packs (e.g. 50 tokens for ₦500) to draft immediately.
                </p>
            </div>
        </div>

        {{-- Step 2 --}}
        <div class="relative pl-8 sm:pl-12 anim-on-scroll anim-delay-1">
            <div class="absolute -left-[17px] top-0 w-8 h-8 rounded-full bg-surface-container border border-primary-container flex items-center justify-center font-mono text-xs font-bold text-primary-container shadow-[0_0_12px_rgba(0,230,118,0.3)]">
                2
            </div>
            <div class="glass hover-lift p-6 sm:p-8 rounded-2xl">
                <span class="font-mono text-xs font-semibold tracking-wider text-primary-container uppercase mb-2 block">Step Two</span>
                <h3 class="font-display font-bold text-xl sm:text-2xl text-on-surface mb-3">Choose Your Match</h3>
                <p class="text-on-surface-variant text-sm sm:text-base leading-relaxed">
                    Browse the match listing for upcoming departmental, inter-level, or faculty clashes. Select the match you want to enter and pay the entry token fee.
                </p>
            </div>
        </div>

        {{-- Step 3 --}}
        <div class="relative pl-8 sm:pl-12 anim-on-scroll anim-delay-2">
            <div class="absolute -left-[17px] top-0 w-8 h-8 rounded-full bg-surface-container border border-primary-container flex items-center justify-center font-mono text-xs font-bold text-primary-container shadow-[0_0_12px_rgba(0,230,118,0.3)]">
                3
            </div>
            <div class="glass hover-lift p-6 sm:p-8 rounded-2xl">
                <span class="font-mono text-xs font-semibold tracking-wider text-primary-container uppercase mb-2 block">Step Three</span>
                <h3 class="font-display font-bold text-xl sm:text-2xl text-on-surface mb-3">Draft a Fresh Squad</h3>
                <p class="text-on-surface-variant text-sm sm:text-base leading-relaxed">
                    Get a virtual budget of <strong class="text-secondary-container">₦100M</strong>. Draft 11 players specifically from the teams competing in that match. Watch out for roster selection limits!
                </p>
            </div>
        </div>

        {{-- Step 4 --}}
        <div class="relative pl-8 sm:pl-12 anim-on-scroll anim-delay-3">
            <div class="absolute -left-[17px] top-0 w-8 h-8 rounded-full bg-surface-container border border-primary-container flex items-center justify-center font-mono text-xs font-bold text-primary-container shadow-[0_0_12px_rgba(0,230,118,0.3)]">
                4
            </div>
            <div class="glass hover-lift p-6 sm:p-8 rounded-2xl">
                <span class="font-mono text-xs font-semibold tracking-wider text-primary-container uppercase mb-2 block">Step Four</span>
                <h3 class="font-display font-bold text-xl sm:text-2xl text-on-surface mb-3">Live Score Tracking</h3>
                <p class="text-on-surface-variant text-sm sm:text-base leading-relaxed">
                    Watch the game live. Your selected players earn fantasy points based on goals, assists, saves, and defensive actions in that specific match.
                </p>
            </div>
        </div>

        {{-- Step 5 --}}
        <div class="relative pl-8 sm:pl-12 anim-on-scroll anim-delay-4">
            <div class="absolute -left-[17px] top-0 w-8 h-8 rounded-full bg-surface-container border border-primary-container flex items-center justify-center font-mono text-xs font-bold text-primary-container shadow-[0_0_12px_rgba(0,230,118,0.3)]">
                5
            </div>
            <div class="glass hover-lift p-6 sm:p-8 rounded-2xl">
                <span class="font-mono text-xs font-semibold tracking-wider text-primary-container uppercase mb-2 block">Step Five</span>
                <h3 class="font-display font-bold text-xl sm:text-2xl text-on-surface mb-3">Claim Match Prizes</h3>
                <p class="text-on-surface-variant text-sm sm:text-base leading-relaxed">
                    Once the final whistle blows, the entry pool collected for that match is distributed directly to the top 3 managers: 50% to 1st, 30% to 2nd, and 20% to 3rd place.
                </p>
            </div>
        </div>

    </div>

    {{-- FAQs --}}
    <div class="mt-24 max-w-4xl mx-auto anim-on-scroll">
        <h2 class="font-display font-bold text-3xl sm:text-4xl text-on-surface text-center mb-12">
            Frequently Asked <span class="text-gradient">Questions</span>
        </h2>

        <div class="space-y-4">
            {{-- FAQ 1 --}}
            <div class="glass rounded-xl overflow-hidden">
                <button class="w-full flex items-center justify-between p-6 text-left font-display font-semibold text-base sm:text-lg text-on-surface hover:text-primary-container transition-colors"
                        data-faq-trigger="faq-1">
                    <span>Is PitchIQ completely free to play?</span>
                    <span class="material-symbols-outlined transition-transform duration-200 text-on-surface-variant" data-faq-icon>add</span>
                </button>
                <div id="faq-1" class="max-h-0 opacity-0 overflow-hidden transition-all duration-300 ease-in-out" data-faq-content>
                    <p class="px-6 pb-6 text-on-surface-variant text-sm sm:text-base leading-relaxed">
                        Yes! Watching a short video ad awards 5 tokens, with a limit of 3 ads (15 tokens) per day. For immediate entries or to play more matches, direct top-up packs start from ₦500 for 50 tokens.
                    </p>
                </div>
            </div>

            {{-- FAQ 2 --}}
            <div class="glass rounded-xl overflow-hidden">
                <button class="w-full flex items-center justify-between p-6 text-left font-display font-semibold text-base sm:text-lg text-on-surface hover:text-primary-container transition-colors"
                        data-faq-trigger="faq-2">
                    <span>How do drafts work?</span>
                    <span class="material-symbols-outlined transition-transform duration-200 text-on-surface-variant" data-faq-icon>add</span>
                </button>
                <div id="faq-2" class="max-h-0 opacity-0 overflow-hidden transition-all duration-300 ease-in-out" data-faq-content>
                    <p class="px-6 pb-6 text-on-surface-variant text-sm sm:text-base leading-relaxed">
                        For every match you enter, you build a brand new squad of 11 players. There are no season-long rosters or transfers to worry about; each match is a fresh start within virtual budget and selection limits.
                    </p>
                </div>
            </div>

            {{-- FAQ 3 --}}
            <div class="glass rounded-xl overflow-hidden">
                <button class="w-full flex items-center justify-between p-6 text-left font-display font-semibold text-base sm:text-lg text-on-surface hover:text-primary-container transition-colors"
                        data-faq-trigger="faq-3">
                    <span>When are draft deadlines?</span>
                    <span class="material-symbols-outlined transition-transform duration-200 text-on-surface-variant" data-faq-icon>add</span>
                </button>
                <div id="faq-3" class="max-h-0 opacity-0 overflow-hidden transition-all duration-300 ease-in-out" data-faq-content>
                    <p class="px-6 pb-6 text-on-surface-variant text-sm sm:text-base leading-relaxed">
                        Drafts close 10 minutes before match kickoff. Once locked, your team cannot be edited, and live tracking starts when the match begins.
                    </p>
                </div>
            </div>

            {{-- FAQ 4 --}}
            <div class="glass rounded-xl overflow-hidden">
                <button class="w-full flex items-center justify-between p-6 text-left font-display font-semibold text-base sm:text-lg text-on-surface hover:text-primary-container transition-colors"
                        data-faq-trigger="faq-4">
                    <span>Can I enter multiple games?</span>
                    <span class="material-symbols-outlined transition-transform duration-200 text-on-surface-variant" data-faq-icon>add</span>
                </button>
                <div id="faq-4" class="max-h-0 opacity-0 overflow-hidden transition-all duration-300 ease-in-out" data-faq-content>
                    <p class="px-6 pb-6 text-on-surface-variant text-sm sm:text-base leading-relaxed">
                        Yes! As long as you have enough tokens (10-50 tokens per entry), you can draft teams and enter multiple different departmental or inter-level games simultaneously.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
