<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

new #[Layout('layouts.app')] class extends Component {

    // Profile fields (email is intentionally NOT editable — it's the login identity)
    public string $name = '';
    public string $phone = '';
    public string $faculty = '';

    // Password change
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    public string $profileStatus = '';
    public string $passwordStatus = '';

    public function mount(): void
    {
        $user = Auth::user();
        $this->name = $user->name;
        $this->phone = $user->phone ?? '';
        $this->faculty = $user->faculty ?? '';
    }

    public function updateProfile(): void
    {
        $validated = $this->validate([
            'name'    => ['required', 'string', 'max:255'],
            'phone'   => ['nullable', 'string', 'regex:' . User::PHONE_REGEX],
            'faculty' => ['nullable', 'string', 'max:100'],
        ], [
            'phone.regex' => 'Enter a valid Nigerian phone number, e.g. 08031234567.',
        ]);

        // Only ever touch these three safe fields — never tokens, referral_code,
        // referred_by, is_admin or email.
        Auth::user()->update([
            'name'    => $validated['name'],
            'phone'   => $validated['phone'] ?: null,
            'faculty' => $validated['faculty'] ?: null,
        ]);

        $this->profileStatus = 'Profile updated.';
    }

    public function updatePassword(): void
    {
        $this->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'current_password.current_password' => 'That doesn’t match your current password.',
        ]);

        Auth::user()->update(['password' => Hash::make($this->password)]);

        $this->reset('current_password', 'password', 'password_confirmation');
        $this->passwordStatus = 'Password changed.';
    }
}; ?>

<div class="max-w-2xl mx-auto px-5 sm:px-8 py-10 space-y-6">

    {{-- Back --}}
    <button type="button" onclick="window.history.back()"
        class="inline-flex items-center gap-1.5 font-mono text-[11px] text-on-surface-variant/60 hover:text-[#00E676] transition-colors cursor-pointer">
        <span class="material-symbols-outlined text-[16px]">arrow_back</span>
        Back
    </button>

    {{-- Header --}}
    <div>
        <h1 class="font-display font-black text-2xl sm:text-3xl text-on-surface tracking-tight">
            Edit <span style="color:#00E676;">Profile</span>
        </h1>
        <p class="font-mono text-xs text-on-surface-variant/60 mt-1">Manage your account details and password.</p>
    </div>

    {{-- ── Profile details card ───────────────────────────────────────────── --}}
    <div class="neo-surface rounded-2xl border border-outline-variant/15 p-6 sm:p-8">
        <form wire:submit.prevent="updateProfile" class="space-y-5" novalidate>

            @if($profileStatus)
                <div class="flex items-start gap-2.5 rounded-xl border border-primary-container/30 px-4 py-3"
                     style="background:rgba(0,230,118,0.06);">
                    <span class="material-symbols-outlined text-primary-container text-[18px]">check_circle</span>
                    <span class="text-xs text-primary-container font-mono">{{ $profileStatus }}</span>
                </div>
            @endif

            {{-- Name --}}
            <div class="flex flex-col gap-2">
                <label for="name" class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider font-mono">Full Name</label>
                <input id="name" type="text" wire:model="name"
                       class="input-field transition-all duration-200 @error('name') border-error/60 focus:border-error @enderror" />
                @error('name') <span class="text-xs text-error font-mono flex items-center gap-1.5"><span class="material-symbols-outlined text-[14px]">warning</span>{{ $message }}</span> @enderror
            </div>

            {{-- Email (locked) --}}
            <div class="flex flex-col gap-2">
                <label class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider font-mono">
                    Email <span class="text-on-surface-variant/50 normal-case font-normal">(login identity — can’t be changed)</span>
                </label>
                <div class="relative">
                    <input type="email" value="{{ auth()->user()->email }}" disabled readonly
                           class="input-field opacity-60 cursor-not-allowed pr-10" />
                    <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-on-surface-variant/40 text-[18px]">lock</span>
                </div>
            </div>

            {{-- Phone --}}
            <div class="flex flex-col gap-2">
                <label for="phone" class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider font-mono">
                    Phone <span class="text-on-surface-variant/50 normal-case font-normal">(used for airtime payouts)</span>
                </label>
                <input id="phone" type="tel" wire:model="phone" placeholder="e.g. 08031234567"
                       class="input-field transition-all duration-200 @error('phone') border-error/60 focus:border-error @enderror" />
                @error('phone') <span class="text-xs text-error font-mono flex items-center gap-1.5"><span class="material-symbols-outlined text-[14px]">warning</span>{{ $message }}</span> @enderror
            </div>

            {{-- Faculty --}}
            <div class="flex flex-col gap-2">
                @php
                    $facultyOptions = collect(config('faculties'));
                    if ($faculty !== '' && !$facultyOptions->contains($faculty)) {
                        $facultyOptions = $facultyOptions->prepend($faculty);
                    }
                @endphp
                <label for="faculty" class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider font-mono">
                    Faculty <span class="text-on-surface-variant/50 normal-case font-normal">(optional)</span>
                </label>
                <select id="faculty" wire:model="faculty"
                        class="input-field cursor-pointer transition-all duration-200 @error('faculty') border-error/60 @enderror">
                    <option value="" class="bg-surface text-on-surface-variant/60">Select your faculty…</option>
                    @foreach($facultyOptions as $f)
                        <option value="{{ $f }}" class="bg-surface text-on-surface">Faculty of {{ $f }}</option>
                    @endforeach
                </select>
                @error('faculty') <span class="text-xs text-error font-mono flex items-center gap-1.5"><span class="material-symbols-outlined text-[14px]">warning</span>{{ $message }}</span> @enderror
            </div>

            <div class="flex justify-end">
                <button type="submit" wire:loading.attr="disabled" wire:target="updateProfile"
                        class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl text-sm font-mono font-bold uppercase tracking-wider text-background bg-primary-container hover:bg-primary-fixed transition-all duration-200 shadow-lg shadow-primary-container/10 hover:scale-[1.01] active:scale-[0.99] cursor-pointer disabled:opacity-50">
                    <span wire:loading.remove wire:target="updateProfile">Save Changes</span>
                    <span wire:loading wire:target="updateProfile">Saving…</span>
                </button>
            </div>
        </form>
    </div>

    {{-- ── Change password card ───────────────────────────────────────────── --}}
    <div class="neo-surface rounded-2xl border border-outline-variant/15 p-6 sm:p-8">
        <div class="mb-5">
            <h2 class="font-display font-bold text-lg text-on-surface">Change Password</h2>
            <p class="font-mono text-[11px] text-on-surface-variant/50 mt-1">Enter your current password to set a new one.</p>
        </div>

        <form wire:submit.prevent="updatePassword" class="space-y-5" novalidate>

            @if($passwordStatus)
                <div class="flex items-start gap-2.5 rounded-xl border border-primary-container/30 px-4 py-3"
                     style="background:rgba(0,230,118,0.06);">
                    <span class="material-symbols-outlined text-primary-container text-[18px]">check_circle</span>
                    <span class="text-xs text-primary-container font-mono">{{ $passwordStatus }}</span>
                </div>
            @endif

            {{-- Current password --}}
            <div class="flex flex-col gap-2">
                <label for="current_password" class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider font-mono">Current Password</label>
                <div class="relative" x-data="{ show: false }">
                    <input id="current_password" :type="show ? 'text' : 'password'" type="password" wire:model="current_password"
                           class="input-field pr-11 w-full @error('current_password') border-error/60 focus:border-error @enderror" />
                    <button type="button" @click="show = !show" tabindex="-1"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-on-surface-variant/50 hover:text-primary-container transition-colors cursor-pointer">
                        <span class="material-symbols-outlined text-[18px]" x-text="show ? 'visibility_off' : 'visibility'">visibility</span>
                    </button>
                </div>
                @error('current_password') <span class="text-xs text-error font-mono flex items-center gap-1.5"><span class="material-symbols-outlined text-[14px]">warning</span>{{ $message }}</span> @enderror
            </div>

            {{-- New password --}}
            <div class="flex flex-col gap-2">
                <label for="password" class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider font-mono">New Password</label>
                <div class="relative" x-data="{ show: false }">
                    <input id="password" :type="show ? 'text' : 'password'" type="password" wire:model="password" placeholder="Min. 8 characters"
                           class="input-field pr-11 w-full @error('password') border-error/60 focus:border-error @enderror" />
                    <button type="button" @click="show = !show" tabindex="-1"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-on-surface-variant/50 hover:text-primary-container transition-colors cursor-pointer">
                        <span class="material-symbols-outlined text-[18px]" x-text="show ? 'visibility_off' : 'visibility'">visibility</span>
                    </button>
                </div>
                @error('password') <span class="text-xs text-error font-mono flex items-center gap-1.5"><span class="material-symbols-outlined text-[14px]">warning</span>{{ $message }}</span> @enderror
            </div>

            {{-- Confirm new password --}}
            <div class="flex flex-col gap-2">
                <label for="password_confirmation" class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider font-mono">Confirm New Password</label>
                <input id="password_confirmation" type="password" wire:model="password_confirmation" placeholder="Repeat new password"
                       class="input-field" />
            </div>

            <div class="flex justify-end">
                <button type="submit" wire:loading.attr="disabled" wire:target="updatePassword"
                        class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl text-sm font-mono font-bold uppercase tracking-wider text-on-surface border border-outline-variant/30 hover:border-primary-container/50 hover:text-primary-container transition-all cursor-pointer disabled:opacity-50">
                    <span wire:loading.remove wire:target="updatePassword">Update Password</span>
                    <span wire:loading wire:target="updatePassword">Updating…</span>
                </button>
            </div>
        </form>
    </div>

    @push('ads')
        @include('partials.propeller-ad')
    @endpush
</div>
