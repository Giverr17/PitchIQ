@extends('layouts.app')

@section('title', 'Privacy Policy')
@section('meta_description', 'How PitchIQ collects, uses, and protects your personal information.')

@section('content')
<div class="max-w-3xl mx-auto px-5 sm:px-8 py-16">
    <div class="mb-10 anim-on-scroll">
        <span class="badge-live mb-4">Legal</span>
        <h1 class="font-display font-black text-4xl sm:text-5xl text-on-surface tracking-tight mb-3">Privacy Policy</h1>
        <p class="font-mono text-xs text-on-surface-variant/60">Last updated: 23 June 2026</p>
    </div>

    <div class="space-y-8 text-on-surface-variant text-sm sm:text-base leading-relaxed anim-on-scroll">
        <section>
            <h2 class="font-display font-bold text-xl text-on-surface mb-2">1. Information We Collect</h2>
            <p>When you create an account we collect your name, email address, and optionally your phone number and faculty. Your phone number is used only to deliver airtime prizes. We also store gameplay data such as your squads, predictions, tokens, and points.</p>
        </section>
        <section>
            <h2 class="font-display font-bold text-xl text-on-surface mb-2">2. How We Use Your Information</h2>
            <p>We use your information to run your account, operate the game, calculate scores and leaderboards, deliver prizes, and communicate important updates such as password resets and prize notifications.</p>
        </section>
        <section>
            <h2 class="font-display font-bold text-xl text-on-surface mb-2">3. Sharing</h2>
            <p>We do not sell your personal data. Limited information may be shared with service providers that help us operate (for example, email delivery and airtime payout providers) strictly to provide those services. Your display name and faculty may appear on public leaderboards.</p>
        </section>
        <section>
            <h2 class="font-display font-bold text-xl text-on-surface mb-2">4. Data Security</h2>
            <p>Passwords are stored hashed, and we take reasonable measures to protect your data. No system is perfectly secure, so please use a strong, unique password.</p>
        </section>
        <section>
            <h2 class="font-display font-bold text-xl text-on-surface mb-2">5. Your Rights</h2>
            <p>You may request access to, correction of, or deletion of your personal data at any time by contacting us at <a href="mailto:support@pitchiq.com" class="text-primary-container hover:underline">support@pitchiq.com</a>.</p>
        </section>
        <section>
            <h2 class="font-display font-bold text-xl text-on-surface mb-2">6. Changes</h2>
            <p>We may update this policy from time to time. Material changes will be reflected by the "last updated" date above.</p>
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
