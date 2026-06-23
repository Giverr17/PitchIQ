<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

new #[Layout('layouts.app')] class extends Component {
    public string $token = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;
        // The reset link carries the email as a query param.
        $this->email = (string) request()->query('email', '');
    }

    public function resetPassword()
    {
        $this->validate([
            'token'    => ['required'],
            'email'    => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        // Password::reset validates the token + email against
        // password_reset_tokens, runs the callback, then deletes the token.
        $status = Password::reset(
            [
                'email'                 => $this->email,
                'password'              => $this->password,
                'password_confirmation' => $this->password_confirmation,
                'token'                 => $this->token,
            ],
            function ($user) {
                $user->forceFill([
                    'password'       => Hash::make($this->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            session()->flash('status', 'Your password has been reset. You can sign in now.');
            return redirect()->route('login');
        }

        $this->addError('email', __($status));
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
                    <span class="material-symbols-outlined text-primary-container text-[26px]">password</span>
                </div>
                <h1 class="font-display font-bold text-2xl text-on-surface mb-1">Reset Password</h1>
                <p class="text-on-surface-variant text-sm">Choose a new password for your account.</p>
            </div>

            {{-- Form --}}
            <form wire:submit.prevent="resetPassword" class="space-y-6" novalidate>

                {{-- Email --}}
                <div class="flex flex-col gap-2">
                    <label for="email" class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider font-mono">
                        Email Address
                    </label>
                    <input id="email"
                           type="email"
                           wire:model="email"
                           class="input-field transition-all duration-200 @error('email') border-error/60 focus:border-error focus:ring-2 focus:ring-error/20 @enderror" />
                    @error('email')
                        <span class="text-xs text-error font-mono flex items-center gap-1.5 mt-1">
                            <span class="material-symbols-outlined text-[14px]">warning</span>
                            {{ $message }}
                        </span>
                    @enderror
                </div>

                {{-- New password --}}
                <div class="flex flex-col gap-2">
                    <label for="password" class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider font-mono">
                        New Password
                    </label>
                    <div class="relative" x-data="{ show: false }">
                        <input id="password"
                               :type="show ? 'text' : 'password'"
                               type="password"
                               wire:model="password"
                               placeholder="••••••••"
                               class="input-field pr-11 w-full transition-all duration-200 @error('password') border-error/60 focus:border-error focus:ring-2 focus:ring-error/20 @enderror" />
                        <button type="button" @click="show = !show" tabindex="-1"
                                :aria-label="show ? 'Hide password' : 'Show password'"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-on-surface-variant/50 hover:text-primary-container transition-colors cursor-pointer">
                            <span class="material-symbols-outlined text-[18px]" x-text="show ? 'visibility_off' : 'visibility'">visibility</span>
                        </button>
                    </div>
                    @error('password')
                        <span class="text-xs text-error font-mono flex items-center gap-1.5 mt-1">
                            <span class="material-symbols-outlined text-[14px]">warning</span>
                            {{ $message }}
                        </span>
                    @enderror
                </div>

                {{-- Confirm password --}}
                <div class="flex flex-col gap-2">
                    <label for="password_confirmation" class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider font-mono">
                        Confirm Password
                    </label>
                    <input id="password_confirmation"
                           type="password"
                           wire:model="password_confirmation"
                           placeholder="••••••••"
                           class="input-field transition-all duration-200" />
                </div>

                <button type="submit" wire:loading.attr="disabled" wire:target="resetPassword"
                        class="w-full py-3.5 px-6 rounded-xl text-sm font-mono font-bold uppercase tracking-wider text-background bg-primary-container hover:bg-primary-fixed transition-all duration-200 shadow-lg shadow-primary-container/10 hover:scale-[1.01] active:scale-[0.99] cursor-pointer disabled:opacity-50">
                    <span wire:loading.remove wire:target="resetPassword">Reset Password</span>
                    <span wire:loading wire:target="resetPassword">Resetting…</span>
                </button>
            </form>

            <p class="text-center text-sm text-on-surface-variant mt-6">
                <a href="{{ route('login') }}" class="font-semibold hover:underline text-primary-container">
                    Back to sign in
                </a>
            </p>
        </div>
    </div>

    @push('ads')
        @include('partials.propeller-ad')
    @endpush
</div>
