@extends('layouts.app')

@section('title', 'Contact Us')
@section('meta_description', 'Get in touch with the PitchIQ team for support, partnerships, or feedback.')

@section('content')
<div class="max-w-3xl mx-auto px-5 sm:px-8 py-16">

    {{-- Header --}}
    <div class="text-center max-w-2xl mx-auto mb-14 anim-on-scroll">
        <span class="badge-live mb-4">Contact</span>
        <h1 class="font-display font-black text-4xl sm:text-5xl text-on-surface tracking-tight mb-6">
            Get In <span class="text-gradient">Touch</span>
        </h1>
        <p class="text-on-surface-variant text-base sm:text-lg leading-relaxed">
            Questions, feedback, or want to bring PitchIQ to your campus? We'd love to hear from you.
        </p>
    </div>

    <div class="grid sm:grid-cols-2 gap-5">
        <a href="mailto:support@pitchiq.com"
           class="glass rounded-2xl p-6 hover-lift anim-on-scroll block">
            <div class="w-10 h-10 rounded-xl bg-primary-container/10 border border-primary-container/25 flex items-center justify-center mb-3">
                <span class="material-symbols-outlined text-primary-container text-[20px]">mail</span>
            </div>
            <h3 class="font-display font-bold text-lg text-on-surface mb-1">Support</h3>
            <p class="text-on-surface-variant text-sm">support@pitchiq.com</p>
        </a>
        <a href="mailto:partnerships@pitchiq.com"
           class="glass rounded-2xl p-6 hover-lift anim-on-scroll anim-delay-1 block">
            <div class="w-10 h-10 rounded-xl bg-primary-container/10 border border-primary-container/25 flex items-center justify-center mb-3">
                <span class="material-symbols-outlined text-primary-container text-[20px]">handshake</span>
            </div>
            <h3 class="font-display font-bold text-lg text-on-surface mb-1">Partnerships</h3>
            <p class="text-on-surface-variant text-sm">partnerships@pitchiq.com</p>
        </a>
    </div>

    <div class="glass rounded-2xl p-6 mt-5 anim-on-scroll text-center">
        <p class="text-on-surface-variant text-sm leading-relaxed">
            Prefer chat? Reach the community and the team on our social channels — linked in the footer.
        </p>
    </div>
</div>
@endsection

@push('ads')
    @include('partials.propeller-ad')
@endpush
