<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;

new #[Layout('layouts.app')] class extends Component {
    public string $email = '';
    public string $password = '';
    public bool $remember = false;

    protected array $rules = [
        'email' => ['required', 'string', 'email'],
        'password' => ['required', 'string'],
    ];

    public function login()
    {
        $this->validate();

        if (Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            session()->regenerate();

            if (Auth::user()->is_admin) {
                return redirect()->route('admin.dashboard');
            }

            return redirect()->intended(route('dashboard'));
        }

        $this->addError('email', 'These credentials do not match our records.');
    }
}; ?>

<div class="min-h-[calc(100vh-140px)] flex items-center justify-center px-4 py-16 relative">
    {{-- Custom error animation styles --}}
    <style>
        @keyframes errorSlideIn {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .error-animate {
            animation: errorSlideIn 0.25s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
    </style>

    {{-- Background grid snippet --}}
    <div class="absolute inset-0 pitch-pattern opacity-10 pointer-events-none"></div>

    <div class="w-full max-w-md relative z-10">

        {{-- Card --}}
        <div class="neo-surface rounded-2xl shadow-2xl p-8 glow-green">

            {{-- Header --}}
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl mb-4 bg-primary-container/10 border border-primary-container/25 shadow-lg">
                    <span class="text-2xl">⚽</span>
                </div>
                <h1 class="font-display font-bold text-2xl text-on-surface mb-1">Welcome Back</h1>
                <p class="text-on-surface-variant text-sm">Sign in to manage your fantasy squad.</p>
            </div>

            {{-- Form --}}
            <form wire:submit.prevent="login" class="space-y-6" novalidate>

                {{-- Email --}}
                <div class="flex flex-col gap-2">
                    <label for="email" class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider font-mono">
                        Email Address
                    </label>
                    <input id="email"
                           type="email"
                           wire:model="email"
                           placeholder="you@example.com"
                           class="input-field transition-all duration-200 @error('email') border-error/60 focus:border-error focus:ring-2 focus:ring-error/20 @enderror"
                           @error('email') style="box-shadow: 0 0 12px rgba(255,180,171,0.08);" @enderror />
                    @error('email')
                        <span class="text-xs text-error font-mono flex items-center gap-1.5 mt-1 error-animate">
                            <span class="material-symbols-outlined text-[14px]">warning</span>
                            {{ $message }}
                        </span>
                    @enderror
                </div>

                {{-- Password --}}
                <div class="flex flex-col gap-2">
                    <div class="flex items-center justify-between">
                        <label for="password" class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider font-mono">
                            Password
                        </label>
                        <a href="#" class="text-xs font-mono hover:underline text-primary-container">Forgot password?</a>
                    </div>
                    <input id="password"
                           type="password"
                           wire:model="password"
                           placeholder="••••••••"
                           class="input-field transition-all duration-200 @error('password') border-error/60 focus:border-error focus:ring-2 focus:ring-error/20 @enderror"
                           @error('password') style="box-shadow: 0 0 12px rgba(255,180,171,0.08);" @enderror />
                    @error('password')
                        <span class="text-xs text-error font-mono flex items-center gap-1.5 mt-1 error-animate">
                            <span class="material-symbols-outlined text-[14px]">warning</span>
                            {{ $message }}
                        </span>
                    @enderror
                </div>

                {{-- Remember Me --}}
                <div class="flex items-center gap-3">
                    <input id="remember"
                           type="checkbox"
                           wire:model="remember"
                           class="w-4 h-4 rounded border-outline-variant/60 bg-surface cursor-pointer focus:ring-0 focus:ring-offset-0"
                           style="accent-color: #00E676;" />
                    <label for="remember" class="text-xs text-on-surface-variant font-mono cursor-pointer select-none">
                        Remember this device
                    </label>
                </div>

                {{-- Submit --}}
                <button type="submit"
                        class="w-full py-3.5 px-6 rounded-xl text-sm font-mono font-bold uppercase tracking-wider text-background bg-primary-container hover:bg-primary-fixed transition-all duration-200 shadow-lg shadow-primary-container/10 hover:shadow-primary-container/20 hover:scale-[1.01] active:scale-[0.99] cursor-pointer">
                    Sign In to Dashboard
                </button>
            </form>

            {{-- Divider --}}
            <div class="my-6 flex items-center gap-4">
                <div class="flex-1 h-px bg-outline-variant/15"></div>
                <span class="text-xs text-on-surface-variant/40 font-mono">OR</span>
                <div class="flex-1 h-px bg-outline-variant/15"></div>
            </div>

            {{-- Register link --}}
            <p class="text-center text-sm text-on-surface-variant">
                Don't have an account?
                <a href="{{ route('register') }}" class="font-semibold hover:underline ml-1 text-primary-container">
                    Create one free
                </a>
            </p>
        </div>
    </div>
</div>
