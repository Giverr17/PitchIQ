<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\User;
use App\Models\Referral;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

new #[Layout('layouts.app')] class extends Component {
    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $faculty = '';
    public string $password = '';
    public string $password_confirmation = '';

    public string $ref = '';          // referral code from ?ref=
    public ?string $referrerName = null;  // shown in the UI if code is valid

    protected array $rules = [
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
        'phone' => ['nullable', 'string', 'max:20'],
        'faculty' => ['nullable', 'string', 'max:100'],
        'password' => ['required', 'string', 'min:8', 'confirmed'],
    ];

    public function mount(): void
    {
        // Capture ?ref=CODE from the URL
        $this->ref = (string) request()->query('ref', '');

        if ($this->ref !== '') {
            $referrer = User::where('referral_code', $this->ref)->first();
            $this->referrerName = $referrer?->name;   // null if code invalid
        }
    }

    public function register()
    {
        $this->validate();

        // Resolve the referrer (if a valid code was used)
        $referrer = null;
        if ($this->ref !== '') {
            $referrer = User::where('referral_code', $this->ref)->first();
        }

        // New user gets the base 20 + a 20 bonus if referred by a valid referrer
        $startingTokens = 20;
        if ($referrer) {
            $startingTokens += 20;   // referral welcome bonus
        }

        $user = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone ?: null,
            'faculty' => $this->faculty ?: null,
            'password' => Hash::make($this->password),
            'tokens' => $startingTokens,
            'referral_code' => User::generateReferralCode(),   // give THEM a code too
            'referred_by' => $referrer?->id,
        ]);

        // Record the referral as pending (referrer rewarded on first squad)
        if ($referrer) {
            Referral::create([
                'referrer_id' => $referrer->id,
                'referred_id' => $user->id,
                'status' => 'pending',
            ]);
        }

        Auth::login($user);
        try {
            \Illuminate\Support\Facades\Mail::to($user->email)
                ->queue(new \App\Mail\WelcomeMail($user, $startingTokens));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Welcome email failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
        $msg = $referrer
            ? "Welcome to PitchIQ! You got {$startingTokens} tokens (20 + 20 referral bonus)."
            : 'Welcome to PitchIQ! You have 20 free tokens.';
        session()->flash('message', $msg);

        return redirect()->route('dashboard');
    }
}; ?>


<div class="min-h-[calc(100vh-140px)] flex items-center justify-center px-4 py-16 relative">
    {{-- Custom error animation styles --}}
    <style>
        @keyframes errorSlideIn {
            from {
                opacity: 0;
                transform: translateY(-8px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
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
                <div
                    class="inline-flex items-center justify-center w-14 h-14 rounded-2xl mb-4 bg-primary-container/10 border border-primary-container/25 shadow-lg">
                    <span class="text-2xl">⚽</span>
                </div>
                <h1 class="font-display font-bold text-2xl text-on-surface mb-1">Create Account</h1>
                <p class="text-on-surface-variant text-sm">Join PitchIQ — 20 free tokens on signup.</p>
                @if($referrerName)
                    <div
                        class="mt-3 inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-primary-container/10 border border-primary-container/25">
                        <span class="material-symbols-outlined text-[14px] text-primary-container">redeem</span>
                        <span class="font-mono text-[11px] text-primary-container">
                            Invited by {{ $referrerName }} — you'll get <strong>40 tokens</strong>!
                        </span>
                    </div>
                @endif
            </div>

            {{-- Form --}}
            <form wire:submit.prevent="register" class="space-y-6" novalidate>

                {{-- Full Name --}}
                <div class="flex flex-col gap-2">
                    <label for="name"
                        class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider font-mono">
                        Full Name
                    </label>
                    <input id="name" type="text" wire:model="name" placeholder="e.g. John Doe"
                        class="input-field transition-all duration-200 @error('name') border-error/60 focus:border-error focus:ring-2 focus:ring-error/20 @enderror"
                        @error('name') style="box-shadow: 0 0 12px rgba(255,180,171,0.08);" @enderror />
                    @error('name')
                        <span class="text-xs text-error font-mono flex items-center gap-1.5 mt-1 error-animate">
                            <span class="material-symbols-outlined text-[14px]">warning</span>
                            {{ $message }}
                        </span>
                    @enderror
                </div>

                {{-- Email --}}
                <div class="flex flex-col gap-2">
                    <label for="email"
                        class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider font-mono">
                        Email Address
                    </label>
                    <input id="email" type="email" wire:model="email" placeholder="you@example.com"
                        class="input-field transition-all duration-200 @error('email') border-error/60 focus:border-error focus:ring-2 focus:ring-error/20 @enderror"
                        @error('email') style="box-shadow: 0 0 12px rgba(255,180,171,0.08);" @enderror />
                    @error('email')
                        <span class="text-xs text-error font-mono flex items-center gap-1.5 mt-1 error-animate">
                            <span class="material-symbols-outlined text-[14px]">warning</span>
                            {{ $message }}
                        </span>
                    @enderror
                </div>

                {{-- Phone (optional) --}}
                <div class="flex flex-col gap-2">
                    <label for="phone"
                        class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider font-mono">
                        Phone Number <span class="text-on-surface-variant/60 normal-case font-normal">(optional — needed
                            for airtime payouts)</span>
                    </label>
                    <input id="phone" type="tel" wire:model="phone" placeholder="e.g. 08012345678"
                        class="input-field transition-all duration-200 @error('phone') border-error/60 focus:border-error focus:ring-2 focus:ring-error/20 @enderror"
                        @error('phone') style="box-shadow: 0 0 12px rgba(255,180,171,0.08);" @enderror />
                    @error('phone')
                        <span class="text-xs text-error font-mono flex items-center gap-1.5 mt-1 error-animate">
                            <span class="material-symbols-outlined text-[14px]">warning</span>
                            {{ $message }}
                        </span>
                    @enderror
                </div>

                {{-- Faculty (optional) --}}
                <div class="flex flex-col gap-2">
                    <label for="faculty"
                        class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider font-mono">
                        Faculty <span class="text-on-surface-variant/60 normal-case font-normal">(optional)</span>
                    </label>
                    <select id="faculty" wire:model="faculty"
                        class="input-field cursor-pointer transition-all duration-200 @error('faculty') border-error/60 focus:border-error focus:ring-2 focus:ring-error/20 @enderror"
                        @error('faculty') style="box-shadow: 0 0 12px rgba(255,180,171,0.08);" @enderror>
                        <option value="" class="bg-surface text-on-surface-variant/60">Select your faculty...</option>
                        @foreach(config('faculties') as $faculty)
                            <option value="{{ $faculty }}" class="bg-surface text-on-surface">Faculty of {{ $faculty }}</option>
                        @endforeach
                    </select>
                    @error('faculty')
                        <span class="text-xs text-error font-mono flex items-center gap-1.5 mt-1 error-animate">
                            <span class="material-symbols-outlined text-[14px]">warning</span>
                            {{ $message }}
                        </span>
                    @enderror
                </div>

                {{-- Password --}}
                <div class="flex flex-col gap-2">
                    <label for="password"
                        class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider font-mono">
                        Password
                    </label>
                    <input id="password" type="password" wire:model="password" placeholder="Min. 8 characters"
                        class="input-field transition-all duration-200 @error('password') border-error/60 focus:border-error focus:ring-2 focus:ring-error/20 @enderror"
                        @error('password') style="box-shadow: 0 0 12px rgba(255,180,171,0.08);" @enderror />
                    @error('password')
                        <span class="text-xs text-error font-mono flex items-center gap-1.5 mt-1 error-animate">
                            <span class="material-symbols-outlined text-[14px]">warning</span>
                            {{ $message }}
                        </span>
                    @enderror
                </div>

                {{-- Confirm Password --}}
                <div class="flex flex-col gap-2">
                    <label for="password_confirmation"
                        class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider font-mono">
                        Confirm Password
                    </label>
                    <input id="password_confirmation" type="password" wire:model="password_confirmation"
                        placeholder="Repeat your password"
                        class="input-field transition-all duration-200 @error('password_confirmation') border-error/60 focus:border-error focus:ring-2 focus:ring-error/20 @enderror"
                        @error('password_confirmation') style="box-shadow: 0 0 12px rgba(255,180,171,0.08);"
                        @enderror />
                    @error('password_confirmation')
                        <span class="text-xs text-error font-mono flex items-center gap-1.5 mt-1 error-animate">
                            <span class="material-symbols-outlined text-[14px]">warning</span>
                            {{ $message }}
                        </span>
                    @enderror
                </div>

                {{-- Submit --}}
                <button type="submit"
                    class="w-full py-3.5 px-6 rounded-xl text-sm font-mono font-bold uppercase tracking-wider text-background bg-primary-container hover:bg-primary-fixed transition-all duration-200 shadow-lg shadow-primary-container/10 hover:shadow-primary-container/20 hover:scale-[1.01] active:scale-[0.99] cursor-pointer">
                    Create Account &amp; Get 20 Tokens
                </button>
            </form>

            {{-- Divider --}}
            <div class="my-6 flex items-center gap-4">
                <div class="flex-1 h-px bg-outline-variant/15"></div>
                <span class="text-xs text-on-surface-variant/40 font-mono">OR</span>
                <div class="flex-1 h-px bg-outline-variant/15"></div>
            </div>

            {{-- Login link --}}
            <p class="text-center text-sm text-on-surface-variant">
                Already have an account?
                <a href="{{ route('login') }}" class="font-semibold hover:underline ml-1 text-primary-container">
                    Sign in instead
                </a>
            </p>
        </div>
    </div>

    @push('ads')
        @include('partials.propeller-ad')
    @endpush
</div>