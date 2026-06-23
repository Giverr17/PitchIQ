@extends('layouts.app')

@section('title', 'Terms of Service')
@section('meta_description', 'The terms and conditions for using PitchIQ.')

@section('content')
<div class="max-w-3xl mx-auto px-5 sm:px-8 py-16">
    <div class="mb-10 anim-on-scroll">
        <span class="badge-live mb-4">Legal</span>
        <h1 class="font-display font-black text-4xl sm:text-5xl text-on-surface tracking-tight mb-3">Terms of Service</h1>
        <p class="font-mono text-xs text-on-surface-variant/60">Last updated: 23 June 2026</p>
    </div>

    <div class="space-y-8 text-on-surface-variant text-sm sm:text-base leading-relaxed anim-on-scroll">
        <section>
            <h2 class="font-display font-bold text-xl text-on-surface mb-2">1. Acceptance</h2>
            <p>By creating an account or using PitchIQ, you agree to these Terms. If you do not agree, please do not use the platform.</p>
        </section>
        <section>
            <h2 class="font-display font-bold text-xl text-on-surface mb-2">2. Eligibility & Accounts</h2>
            <p>You must provide accurate information and keep your login credentials secure. One account per person. You are responsible for activity under your account.</p>
        </section>
        <section>
            <h2 class="font-display font-bold text-xl text-on-surface mb-2">3. Tokens & Gameplay</h2>
            <p>Tokens are an in-game currency with no cash value and are non-transferable. They are used to enter games such as predictions and squad drafting. We may adjust token costs, rewards, and game mechanics to keep the game fair and balanced.</p>
        </section>
        <section>
            <h2 class="font-display font-bold text-xl text-on-surface mb-2">4. Prizes</h2>
            <p>Prizes vary by tournament and may include airtime, cash, or other rewards as stated by the organisers. Prizes are awarded based on final, verified results and are subject to fair-play checks.</p>
        </section>
        <section>
            <h2 class="font-display font-bold text-xl text-on-surface mb-2">5. Fair Play</h2>
            <p>Cheating, multiple accounts, exploiting bugs, or manipulating results is prohibited and may result in suspension, disqualification, and forfeiture of tokens or prizes.</p>
        </section>
        <section>
            <h2 class="font-display font-bold text-xl text-on-surface mb-2">6. Liability</h2>
            <p>PitchIQ is provided "as is". To the extent permitted by law, we are not liable for losses arising from use of the platform, downtime, or errors in scoring that are corrected once identified.</p>
        </section>
        <section>
            <h2 class="font-display font-bold text-xl text-on-surface mb-2">7. Contact</h2>
            <p>Questions about these Terms? Email <a href="mailto:support@pitchiq.com" class="text-primary-container hover:underline">support@pitchiq.com</a>.</p>
        </section>
    </div>

    <div class="mt-12 anim-on-scroll">
        <a href="{{ route('home') }}" class="inline-flex items-center gap-2 text-sm font-mono text-primary-container hover:underline">
            <span class="material-symbols-outlined text-[16px]">arrow_back</span> Back home
        </a>
    </div>
</div>
@endsection

@push('ads')
    @include('partials.propeller-ad')
@endpush
