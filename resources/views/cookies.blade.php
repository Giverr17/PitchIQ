@extends('layouts.app')

@section('title', 'Cookie Policy')
@section('meta_description', 'How PitchIQ uses cookies and similar technologies.')

@section('content')
<div class="max-w-3xl mx-auto px-5 sm:px-8 py-16">
    <div class="mb-10 anim-on-scroll">
        <span class="badge-live mb-4">Legal</span>
        <h1 class="font-display font-black text-4xl sm:text-5xl text-on-surface tracking-tight mb-3">Cookie Policy</h1>
        <p class="font-mono text-xs text-on-surface-variant/60">Last updated: 23 June 2026</p>
    </div>

    <div class="space-y-8 text-on-surface-variant text-sm sm:text-base leading-relaxed anim-on-scroll">
        <section>
            <h2 class="font-display font-bold text-xl text-on-surface mb-2">1. What Are Cookies</h2>
            <p>Cookies are small files stored on your device that help websites work and remember information about your visit.</p>
        </section>
        <section>
            <h2 class="font-display font-bold text-xl text-on-surface mb-2">2. How We Use Them</h2>
            <p>PitchIQ uses essential cookies to keep you signed in, secure your session, and protect against cross-site request forgery. These are required for the platform to function.</p>
        </section>
        <section>
            <h2 class="font-display font-bold text-xl text-on-surface mb-2">3. Managing Cookies</h2>
            <p>You can clear or block cookies in your browser settings, but disabling essential cookies may prevent you from signing in or using parts of the platform.</p>
        </section>
        <section>
            <h2 class="font-display font-bold text-xl text-on-surface mb-2">4. Contact</h2>
            <p>Questions about cookies? Email <a href="mailto:support@pitchiq.com" class="text-primary-container hover:underline">support@pitchiq.com</a>.</p>
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
