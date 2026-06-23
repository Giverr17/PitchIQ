<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Password;

new #[Layout('layouts.app')] class extends Component {
    public string $email = '';
    public string $status = '';

    public function sendResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        // Laravel's password broker creates the token, stores it in
        // password_reset_tokens, and emails the reset link.
        $result = Password::sendResetLink(['email' => $this->email]);

        if ($result === Password::RESET_LINK_SENT) {
            $this->status = 'If that email is registered, a reset link is on its way.';
            $this->reset('email');
            return;
        }

        $this->addError('email', __($result));
    }
}; ?>

<div class="min-h-[calc(100vh-140px)] flex items-center justify-center px-4 py-16 relative">

    {{-- Background grid snippet --}}
    <div class="absolute inset-0 pitch-pattern opacity-10 pointer-events-none"></div>

    <div class="w-full max-w-md relative z-10">
        <div class="neo-surface rounded-2xl shadow-2xl p-8 glow-green">

            {{-- Header --}}
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl mb-4 bg-primary-container/10 border border-primary-container/25 shadow-lg">
                    <span class="material-symbols-outlined text-primary-container text-[26px]">lock_reset</span>
                </div>
                <h1 class="font-display font-bold text-2xl text-on-surface mb-1">Forgot Password</h1>
                <p class="text-on-surface-variant text-sm">Enter your email and we'll send you a reset link.</p>
            </div>

            {{-- Success status --}}
            @if($status)
                <div class="mb-6 flex items-start gap-2.5 rounded-xl border border-primary-container/30 px-4 py-3"
                     style="background:rgba(0,230,118,0.06);">
                    <span class="material-symbols-outlined text-primary-container text-[18px]">mark_email_read</span>
                    <span class="text-xs text-primary-container font-mono">{{ $status }}</span>
                </div>
            @endif

            {{-- Form --}}
            <form wire:submit.prevent="sendResetLink" class="space-y-6" novalidate>
                <div class="flex flex-col gap-2">
                    <label for="email" class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider font-mono">
                        Email Address
                    </label>
                    <input id="email"
                           type="email"
                           wire:model="email"
                           placeholder="you@example.com"
                           class="input-field transition-all duration-200 @error('email') border-error/60 focus:border-error focus:ring-2 focus:ring-error/20 @enderror" />
                    @error('email')
                        <span class="text-xs text-error font-mono flex items-center gap-1.5 mt-1">
                            <span class="material-symbols-outlined text-[14px]">warning</span>
                            {{ $message }}
                        </span>
                    @enderror
                </div>

                <button type="submit" wire:loading.attr="disabled" wire:target="sendResetLink"
                        class="w-full py-3.5 px-6 rounded-xl text-sm font-mono font-bold uppercase tracking-wider text-background bg-primary-container hover:bg-primary-fixed transition-all duration-200 shadow-lg shadow-primary-container/10 hover:scale-[1.01] active:scale-[0.99] cursor-pointer disabled:opacity-50">
                    <span wire:loading.remove wire:target="sendResetLink">Send Reset Link</span>
                    <span wire:loading wire:target="sendResetLink">Sending…</span>
                </button>
            </form>

            {{-- Back to login --}}
            <p class="text-center text-sm text-on-surface-variant mt-6">
                Remembered it?
                <a href="{{ route('login') }}" class="font-semibold hover:underline ml-1 text-primary-container">
                    Back to sign in
                </a>
            </p>
        </div>
    </div>

    @push('ads')
        @include('partials.propeller-ad')
    @endpush
</div>
