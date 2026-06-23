<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Lazy;
use App\Models\Fixture;
use App\Models\FantasyTeam;
use App\Models\Prediction;
use App\Models\Tournament;
use Illuminate\Support\Facades\Auth;

new #[Layout('layouts.app'), Lazy] class extends Component {

    public int $userTokens = 0;
    public int $totalPoints = 0;
    public $fixtures = [];
    public $activeTournament = null;
    public $fantasyTeam = null;
    public $userRank = null;
    public $totalManagers = null;
    public string $adMessage = '';
    public string $adMessageType = 'success';

    // Ad reward config
    public int $adReward = 15;
    public int $adCooldownSeconds = 90;
    public int $adsPerHour = 5;
    public int $adsPerDay = 20;
    public int $cooldownRemaining = 0;

    public string $referralCode = '';
    public string $referralLink = '';
    public int $referralsJoined = 0;
    public int $referralsRewarded = 0;
    public int $referralTokensEarned = 0;

    public function placeholder(): string
    {
        return <<<'HTML'
        <div class="max-w-5xl mx-auto px-5 sm:px-8 py-10">
            <div class="h-8 shimmer rounded-xl w-48 mb-8"></div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-8">
                <div class="h-24 shimmer rounded-2xl"></div>
                <div class="h-24 shimmer rounded-2xl"></div>
                <div class="h-24 shimmer rounded-2xl"></div>
                <div class="h-24 shimmer rounded-2xl"></div>
            </div>
            <div class="h-48 shimmer rounded-2xl mb-4"></div>
            <div class="h-48 shimmer rounded-2xl"></div>
        </div>
        HTML;
    }

    public function mount(): void
    {
        $user = Auth::user();
        $this->userTokens = $user->tokens;

        // Referral data
        $this->referralCode = $user->referral_code ?? '';
        $this->referralLink = route('register') . '?ref=' . $this->referralCode;
        $this->referralsJoined = $user->referredUsers()->count();
        $this->referralsRewarded = \App\Models\Referral::where('referrer_id', $user->id)
            ->where('status', 'completed')
            ->count();
        $this->referralTokensEarned = $this->referralsRewarded * 20;

        // Active tournament
        $this->activeTournament = Tournament::where('status', 'active')->latest()->first()?->toArray();

        // Upcoming fixtures (next 5) — only those in the active matchday of their
        // active tournament, so the "Predict" deep-link always resolves on the
        // predictions page (mirrors the predictions + squad-builder filtering).
        $this->fixtures = Fixture::with(['homeTeam', 'awayTeam', 'tournament'])
            ->whereIn('status', ['scheduled', 'live'])
            ->whereHas('tournament', fn($q) => $q
                ->where('status', \App\Enums\TournamentStatus::Active)
                ->whereColumn('fixtures.matchday', 'tournaments.active_matchday'))
            ->orderBy('date')
            ->limit(5)
            ->get()
            ->toArray();

        if ($this->activeTournament) {
            $this->fantasyTeam = FantasyTeam::where('user_id', $user->id)
                ->where('tournament_id', $this->activeTournament['id'])
                ->first()?->toArray();

            $this->computePointsAndRank($user->id, $this->activeTournament['id']);
        }
        $lastAd = \App\Models\TokenTransaction::where('user_id', $user->id)
            ->where('type', \App\Enums\TokenTransactionType::Earned)
            ->where('description', 'Ad reward')
            ->latest()
            ->first();

        if ($lastAd) {
            $elapsed = $lastAd->created_at->diffInSeconds(now());
            $this->cooldownRemaining = max(0, $this->adCooldownSeconds - $elapsed);
        }

        $this->claimDailyBonus();
    }

    private function claimDailyBonus(): void
    {
        $user = Auth::user();

        // Once per calendar day — has the user already claimed today's bonus?
        $alreadyClaimed = \App\Models\TokenTransaction::where('user_id', $user->id)
            ->where('description', 'Daily login bonus')
            ->whereDate('created_at', now()->toDateString())
            ->exists();

        if ($alreadyClaimed) {
            return;   // already got it today, do nothing
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($user) {
            $user->increment('tokens', 10);

            \App\Models\TokenTransaction::create([
                'user_id' => $user->id,
                'type' => \App\Enums\TokenTransactionType::Earned,
                'amount' => 10,
                'description' => 'Daily login bonus',
            ]);
        });

        // Surface a small toast so the user knows they got it
        session()->flash('daily_bonus', '🎁 +10 daily bonus tokens! Welcome back.');
    }

    public function refreshTokens(): void
    {
        $this->userTokens = Auth::user()->fresh()->tokens;
    }

    public function refreshPoints(): void
    {
        $user = Auth::user();
        if (!$this->activeTournament) {
            return;
        }

        $this->fantasyTeam = FantasyTeam::where('user_id', $user->id)
            ->where('tournament_id', $this->activeTournament['id'])
            ->first()?->toArray();

        $this->computePointsAndRank($user->id, $this->activeTournament['id']);
    }
    public function watchAd(): void
    {
        $user = Auth::user();
        $now = now();

        // Pull this user's recent ad-earning transactions (one query, reused for all checks)
        $recentAds = \App\Models\TokenTransaction::where('user_id', $user->id)
            ->where('type', \App\Enums\TokenTransactionType::Earned)
            ->where('description', 'Ad reward')
            ->where('created_at', '>=', $now->copy()->subDay())
            ->orderByDesc('created_at')
            ->get();

        // ─── Gate 1: cooldown ────────────────────────────────────────────────
        $lastAd = $recentAds->first();
        if ($lastAd && $lastAd->created_at->diffInSeconds($now) < $this->adCooldownSeconds) {
            $wait = $this->adCooldownSeconds - $lastAd->created_at->diffInSeconds($now);
            $this->flashAd("Please wait {$wait}s before watching another ad.", 'error');
            return;
        }

        // ─── Gate 2: hourly cap ──────────────────────────────────────────────
        $inLastHour = $recentAds->where('created_at', '>=', $now->copy()->subHour())->count();
        if ($inLastHour >= $this->adsPerHour) {
            $this->flashAd("Hourly limit reached ({$this->adsPerHour}/hour). Try again later.", 'error');
            return;
        }

        // ─── Gate 3: daily cap ───────────────────────────────────────────────
        if ($recentAds->count() >= $this->adsPerDay) {
            $this->flashAd("Daily limit reached ({$this->adsPerDay}/day). Come back tomorrow.", 'error');
            return;
        }

        // ─── All gates passed: credit the tokens ─────────────────────────────
        \Illuminate\Support\Facades\DB::transaction(function () use ($user) {
            $user->increment('tokens', $this->adReward);

            \App\Models\TokenTransaction::create([
                'user_id' => $user->id,
                'type' => \App\Enums\TokenTransactionType::Earned,
                'amount' => $this->adReward,
                'description' => 'Ad reward',
            ]);
        });

        $this->userTokens = $user->fresh()->tokens;
        $this->flashAd("+{$this->adReward} tokens earned!", 'success');
        $this->cooldownRemaining = $this->adCooldownSeconds;
    }

    private function flashAd(string $msg, string $type): void
    {
        $this->adMessage = $msg;
        $this->adMessageType = $type;
    }
    private function computePointsAndRank(int $userId, int $tournamentId): void
    {
        // Fantasy points: sum all teams the user has in this tournament (one per fixture entered)
        $allFantasy = FantasyTeam::where('tournament_id', $tournamentId)
            ->selectRaw('user_id, SUM(total_points) as ft_sum')
            ->groupBy('user_id')
            ->pluck('ft_sum', 'user_id');

        // Prediction points: sum all verified predictions in this tournament per user
        $allPreds = Prediction::whereHas('fixture', fn($q) => $q->where('tournament_id', $tournamentId))
            ->whereNotNull('verified_at')
            ->selectRaw('user_id, SUM(points_earned) as pred_sum')
            ->groupBy('user_id')
            ->pluck('pred_sum', 'user_id');

        // Combined totals for every participant
        $allUids = $allFantasy->keys()->merge($allPreds->keys())->unique();
        $allTotals = $allUids->mapWithKeys(fn($uid) => [
            $uid => ($allFantasy[$uid] ?? 0) + ($allPreds[$uid] ?? 0),
        ]);

        $myTotal = $allTotals->get($userId, 0);
        $this->totalPoints = $myTotal;
        $this->userRank = $allTotals->filter(fn($t) => $t > $myTotal)->count() + 1;
        $this->totalManagers = $allTotals->count();
    }
} ?>

<div class="max-w-7xl mx-auto px-5 sm:px-8 py-10 space-y-8">

    @if(session('daily_bonus'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
            class="p-3 rounded-xl border border-[#00E676]/40 bg-[#00E676]/10 text-[#00E676] font-mono text-xs font-semibold">
            {{ session('daily_bonus') }}
        </div>
    @endif
    {{-- Flash message --}}
    @if(session()->has('message'))
        <div class="p-4 rounded-2xl border flex items-center justify-between gap-4 bg-[#00E676]/10 border-[#00E676]/30 text-[#00E676]"
            x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)">
            <div class="flex items-center gap-2.5">
                <span class="material-symbols-outlined text-[18px]">check_circle</span>
                <span class="font-mono text-xs font-semibold">{{ session('message') }}</span>
            </div>
            <button @click="show = false" class="text-[#00E676]/60 hover:text-[#00E676] cursor-pointer">&times;</button>
        </div>
    @endif
    {{-- ── Ad-reward toast (floating: bottom-centre on mobile, top-right on desktop) ── --}}
    @if($adMessage)
        <div x-data="{ show: true }" x-show="show" x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-3" x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0" x-init="setTimeout(() => show = false, 4000)"
            class="fixed z-[60] inset-x-4 bottom-4 sm:inset-x-auto sm:bottom-auto sm:top-20 sm:right-4 sm:max-w-sm
                                               p-4 rounded-2xl border shadow-2xl backdrop-blur-md flex items-center gap-2.5
                                               {{ $adMessageType === 'success' ? 'bg-[#00E676]/15 border-[#00E676]/40 text-[#00E676]' : 'bg-amber-500/15 border-amber-500/40 text-amber-400' }}">
            <span
                class="material-symbols-outlined text-[18px]">{{ $adMessageType === 'success' ? 'check_circle' : 'schedule' }}</span>
            <span class="font-mono text-xs font-semibold">{{ $adMessage }}</span>
        </div>
    @endif

    {{-- Welcome banner --}}
    <div class="rounded-2xl border border-outline-variant/15 p-6 sm:p-8 relative overflow-hidden"
        style="background: linear-gradient(135deg, rgba(0,230,118,0.06) 0%, rgba(13,17,15,0.9) 60%);">
        <div class="absolute right-0 top-0 w-64 h-64 rounded-full pointer-events-none"
            style="background: radial-gradient(circle, rgba(0,230,118,0.05) 0%, transparent 70%);"></div>

        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-6 relative z-10">
            <div>
                <p class="font-mono text-[10px] uppercase tracking-widest text-on-surface-variant/50 mb-1">Manager
                    Dashboard</p>
                <h1 class="font-display font-black text-2xl sm:text-3xl text-white mb-1">
                    Welcome, <span style="color:#00E676;">{{ Auth::user()->name }}</span>
                </h1>
                @if(Auth::user()->faculty)
                    <p class="font-mono text-xs text-on-surface-variant/60 uppercase tracking-wider">
                        {{ Auth::user()->faculty }}
                    </p>
                @endif
            </div>

            {{-- Token balance — polls every 5 s so deductions from other pages show up automatically --}}
            <div class="flex flex-wrap items-center gap-3 px-5 py-4 rounded-xl border border-outline-variant/20"
                wire:poll.5s="refreshTokens" style="background: rgba(255,255,255,0.03);">
                <div class="text-center">
                    <span class="block font-black text-3xl font-mono" style="color:#00E676;">
                        {{ $userTokens }}
                    </span>
                    <span
                        class="font-mono text-[9px] uppercase tracking-widest text-on-surface-variant/50">Tokens</span>
                </div>
                <div class="w-px h-10 bg-outline-variant/20"></div>

                <!-- {{-- Watch ad button --}}
                {{-- Replace the watch-ad button wrapper with this --}}
                <div x-data="{
        remaining: @entangle('cooldownRemaining'),
        adPlaying: false,
        adCountdown: 5,
        playTestAd() {
            this.adPlaying = true;
            this.adCountdown = 5;
            const timer = setInterval(() => {
                this.adCountdown--;
                if (this.adCountdown <= 0) {
                    clearInterval(timer);
                    this.adPlaying = false;
                    $wire.watchAd();   // ← fires the SAME method the real ad will
                }
            }, 1000);
        }
     }" x-init="setInterval(() => { if (remaining > 0) remaining-- }, 1000)">

                    {{-- The button --}}
                    <button x-on:click="playTestAd()" x-bind:disabled="remaining > 0 || adPlaying"
                        class="px-4 py-2.5 rounded-xl text-xs font-mono font-bold uppercase tracking-wider transition-all flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                        x-bind:class="(remaining > 0 || adPlaying)
                ? 'text-on-surface-variant/50 border border-outline-variant/20 cursor-not-allowed'
                : 'text-white border border-outline-variant/20 hover:border-[#00E676]/40 cursor-pointer'">
                        <span class="material-symbols-outlined text-[16px]">smart_display</span>
                        <span x-show="remaining <= 0 && !adPlaying">Watch Ad +{{ $adReward }}</span>
                        <span x-show="remaining > 0" x-text="`Wait ${remaining}s`"></span>
                        <span x-show="adPlaying">Playing...</span>
                    </button>

                    {{-- Fake ad modal (TEST ONLY — swap for PropellerAds later) --}}
                    <div x-show="adPlaying" x-cloak class="fixed inset-0 z-50 flex items-center justify-center"
                        style="background: rgba(0,0,0,0.85);">
                        <div class="text-center">
                            <div
                                class="w-20 h-20 rounded-full border-4 border-[#00E676] border-t-transparent animate-spin mx-auto mb-6">
                            </div>
                            <p class="font-mono text-sm text-white mb-2">Test ad playing...</p>
                            <p class="font-mono text-3xl font-black" style="color:#00E676;" x-text="adCountdown"></p>
                            <p class="font-mono text-[10px] text-on-surface-variant/40 mt-4 uppercase tracking-widest">
                                Simulated — replace with PropellerAds</p>
                        </div>
                    </div>
                </div> -->

                <a href="{{ route('squad.builder') }}"
                    class="px-4 py-2.5 rounded-xl text-xs font-mono font-bold uppercase tracking-wider text-black cursor-pointer"
                    style="background: linear-gradient(135deg, #00E676 0%, #00b359 100%);">
                    Build Squad
                </a>
            </div>
        </div>
    </div>

    {{-- Stat cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4" wire:poll.30s="refreshPoints">

        {{-- Fantasy points --}}
        <div class="rounded-xl border border-outline-variant/15 p-5" style="background: rgba(13,17,15,0.8);">
            <p class="font-mono text-[10px] uppercase tracking-wider text-on-surface-variant/50 mb-2">My Points</p>
            <p class="font-black text-3xl font-mono" style="color:#00E676;">
                {{ $fantasyTeam ? $totalPoints : '—' }}
            </p>
            <p class="font-mono text-[10px] text-on-surface-variant/40 mt-1">
                {{ $fantasyTeam ? $fantasyTeam['team_name'] : 'No squad yet' }}
            </p>
        </div>

        {{-- Rank --}}
        <div class="rounded-xl border border-outline-variant/15 p-5" style="background: rgba(13,17,15,0.8);">
            <p class="font-mono text-[10px] uppercase tracking-wider text-on-surface-variant/50 mb-2">Rank</p>
            <p class="font-black text-3xl font-mono text-white">
                {{ $userRank ? '#' . $userRank : '—' }}
            </p>
            <p class="font-mono text-[10px] text-on-surface-variant/40 mt-1">
                {{ $totalManagers ? 'of ' . $totalManagers . ' managers' : 'Join a tournament' }}
            </p>
        </div>

        {{-- Active tournament --}}
        <div class="rounded-xl border border-outline-variant/15 p-5" style="background: rgba(13,17,15,0.8);">
            <p class="font-mono text-[10px] uppercase tracking-wider text-on-surface-variant/50 mb-2">Tournament</p>
            <p class="font-black text-sm text-white leading-tight">
                {{ $activeTournament ? $activeTournament['name'] : 'None active' }}
            </p>
            <p class="font-mono text-[10px] text-on-surface-variant/40 mt-1">
                {{ $activeTournament ? $activeTournament['season'] : '—' }}
            </p>
        </div>

        {{-- Upcoming fixtures count --}}
        <div class="rounded-xl border border-outline-variant/15 p-5" style="background: rgba(13,17,15,0.8);">
            <p class="font-mono text-[10px] uppercase tracking-wider text-on-surface-variant/50 mb-2">Upcoming</p>
            <p class="font-black text-3xl font-mono text-white">{{ count($fixtures) }}</p>
            <p class="font-mono text-[10px] text-on-surface-variant/40 mt-1">fixtures scheduled</p>
        </div>
    </div>

    {{-- Main content --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Upcoming fixtures --}}
        <div class="lg:col-span-2 space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="font-display font-black text-lg text-white uppercase tracking-tight">Upcoming Fixtures</h2>
                <a href="{{ route('leaderboard') }}"
                    class="font-mono text-xs text-on-surface-variant/50 hover:text-[#00E676] transition-colors">
                    View leaderboard →
                </a>
            </div>

            @forelse($fixtures as $fixture)
                @php
                    $status = is_array($fixture['status']) ? $fixture['status']['value'] : $fixture['status'];
                    $isLive = $status === 'live';
                @endphp
                <div class="rounded-xl border p-5 transition-all duration-150 {{ $isLive ? 'border-red-500/30' : 'border-outline-variant/15' }}"
                    style="background: rgba(13,17,15,0.8);">
                    <div class="flex items-center justify-between gap-4">

                        {{-- Teams --}}
                        <div class="flex items-center gap-3 flex-1 min-w-0">
                            {{-- Home team colour dot --}}
                            <span class="w-2.5 h-2.5 rounded-full flex-shrink-0"
                                style="background: {{ $fixture['home_team']['colour'] }};"></span>

                            <div class="min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-bold text-white text-sm">{{ $fixture['home_team']['name'] }}</span>
                                    <span class="text-on-surface-variant/40 font-mono text-xs">vs</span>
                                    <span class="font-bold text-white text-sm">{{ $fixture['away_team']['name'] }}</span>
                                </div>
                                <p class="font-mono text-[10px] text-on-surface-variant/40 mt-0.5">
                                    MD{{ $fixture['matchday'] }} ·
                                    {{ $fixture['date'] ? \Carbon\Carbon::parse($fixture['date'])->format('d M · H:i') : 'TBC' }}
                                </p>
                            </div>
                        </div>

                        {{-- Status + predict CTA --}}
                        <div class="flex items-center gap-3 flex-shrink-0">
                            @if($isLive)
                                <span
                                    class="px-2.5 py-0.5 rounded-full font-mono text-[9px] font-bold uppercase tracking-widest border animate-pulse"
                                    style="background:rgba(239,68,68,0.08);color:#f87171;border-color:rgba(239,68,68,0.2);">
                                    Live
                                </span>
                            @else
                                <a href="{{ route('predictions.index', ['fixture_id' => $fixture['id']]) }}"
                                    class="px-3 py-1.5 rounded-lg text-[10px] font-mono font-bold uppercase tracking-wider border transition-all"
                                    style="background:rgba(0,230,118,0.08);color:#00E676;border-color:rgba(0,230,118,0.25);">
                                    Predict
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-outline-variant/15 p-10 text-center"
                    style="background: rgba(13,17,15,0.8);">
                    <span
                        class="material-symbols-outlined text-3xl text-on-surface-variant/20 block mb-2">calendar_month</span>
                    <p class="font-mono text-xs text-on-surface-variant/40">No upcoming fixtures yet.</p>
                </div>
            @endforelse
        </div>

        {{-- Right sidebar --}}
        <div class="space-y-5">

            {{-- Squad status --}}
            <div class="rounded-xl border border-outline-variant/15 p-5" style="background: rgba(13,17,15,0.8);">
                <h3 class="font-display font-black text-sm uppercase tracking-wider text-white mb-4">My Squad</h3>

                @if($fantasyTeam)
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="font-mono text-xs text-on-surface-variant/60">Team name</span>
                            <span class="font-mono text-xs font-bold text-white">{{ $fantasyTeam['team_name'] }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="font-mono text-xs text-on-surface-variant/60">Total points</span>
                            <span class="font-mono text-xs font-bold" style="color:#00E676;">{{ $totalPoints }} pts</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="font-mono text-xs text-on-surface-variant/60">Budget left</span>
                            <span class="font-mono text-xs font-bold text-white">{{ $fantasyTeam['budget_remaining'] }}
                                pts</span>
                        </div>
                        <a href="{{ route('squad.builder') }}"
                            class="block w-full text-center py-2.5 rounded-xl text-xs font-mono font-bold uppercase tracking-wider text-black mt-2"
                            style="background: linear-gradient(135deg, #00E676 0%, #00b359 100%);">
                            Manage Squad
                        </a>
                    </div>
                @else
                    <div class="text-center py-4">
                        <span
                            class="material-symbols-outlined text-3xl text-on-surface-variant/20 block mb-2">group_add</span>
                        <p class="font-mono text-xs text-on-surface-variant/40 mb-4">You haven't built a squad yet.</p>
                        <a href="{{ route('squad.builder') }}"
                            class="inline-block px-5 py-2.5 rounded-xl text-xs font-mono font-bold uppercase tracking-wider text-black"
                            style="background: linear-gradient(135deg, #00E676 0%, #00b359 100%);">
                            Build Squad
                        </a>
                    </div>
                @endif
            </div>

            {{-- Referral card --}}
            <div class="rounded-xl border border-[#00E676]/20 p-5"
                style="background: linear-gradient(135deg, rgba(0,230,118,0.06) 0%, rgba(13,17,15,0.8) 70%);"
                x-data="{ copied: false }">
                <div class="flex items-center gap-2 mb-1">
                    <span class="material-symbols-outlined text-[18px]" style="color:#00E676;">redeem</span>
                    <h3 class="font-display font-black text-sm uppercase tracking-wider text-white">Invite Friends</h3>
                </div>
                <p class="font-mono text-[10px] text-on-surface-variant/50 mb-4">
                    They get 40 tokens, you get 20 when they build their first squad.
                </p>

                {{-- Stats --}}
                <div class="grid grid-cols-3 gap-2 mb-4">
                    <div class="text-center rounded-lg border border-outline-variant/15 py-2"
                        style="background:rgba(255,255,255,0.02);">
                        <span class="block font-black text-lg font-mono text-white">{{ $referralsJoined }}</span>
                        <span
                            class="font-mono text-[8px] uppercase tracking-widest text-on-surface-variant/50">Joined</span>
                    </div>
                    <div class="text-center rounded-lg border border-outline-variant/15 py-2"
                        style="background:rgba(255,255,255,0.02);">
                        <span class="block font-black text-lg font-mono"
                            style="color:#00E676;">{{ $referralsRewarded }}</span>
                        <span
                            class="font-mono text-[8px] uppercase tracking-widest text-on-surface-variant/50">Active</span>
                    </div>
                    <div class="text-center rounded-lg border border-outline-variant/15 py-2"
                        style="background:rgba(255,255,255,0.02);">
                        <span class="block font-black text-lg font-mono"
                            style="color:#00E676;">+{{ $referralTokensEarned }}</span>
                        <span
                            class="font-mono text-[8px] uppercase tracking-widest text-on-surface-variant/50">Earned</span>
                    </div>
                </div>

                {{-- Code --}}
                <div class="mb-3">
                    <span
                        class="font-mono text-[9px] uppercase tracking-widest text-on-surface-variant/50 block mb-1">Your
                        code</span>
                    <span class="font-mono font-black text-base tracking-widest"
                        style="color:#00E676;">{{ $referralCode }}</span>
                </div>

                {{-- Link + copy --}}
                <div class="flex items-center gap-2">
                    <input type="text" readonly value="{{ $referralLink }}" x-ref="reflink"
                        class="flex-1 min-w-0 px-3 py-2 rounded-lg text-[10px] font-mono text-on-surface-variant/70 border border-outline-variant/20 bg-white/5 focus:outline-none truncate" />
                    <button
                        x-on:click="navigator.clipboard.writeText($refs.reflink.value); copied = true; setTimeout(() => copied = false, 2000)"
                        class="px-3 py-2 rounded-lg text-[10px] font-mono font-bold uppercase tracking-wider text-black flex-shrink-0 cursor-pointer transition-all"
                        style="background: linear-gradient(135deg, #00E676 0%, #00b359 100%);">
                        <span x-show="!copied">Copy</span>
                        <span x-show="copied" x-cloak>Copied!</span>
                    </button>
                </div>

                {{-- WhatsApp share --}}
                <a :href="`https://wa.me/?text=${encodeURIComponent('Join me on PitchIQ — campus fantasy football! Use my link and get 40 free tokens: {{ $referralLink }}')}`"
                    target="_blank" x-data
                    class="mt-2 flex items-center justify-center gap-2 w-full py-2 rounded-lg text-[10px] font-mono font-bold uppercase tracking-wider border border-[#00E676]/30 text-[#00E676] hover:bg-[#00E676]/10 transition-all">
                    <span class="material-symbols-outlined text-[14px]">share</span>
                    Share on WhatsApp
                </a>
            </div>

            {{-- Quick links --}}
            <div class="rounded-xl border border-outline-variant/15 p-5" style="background: rgba(13,17,15,0.8);">
                <h3 class="font-display font-black text-sm uppercase tracking-wider text-white mb-4">Quick Links</h3>
                <div class="space-y-2">
                    @foreach([
                            ['route' => 'leaderboard', 'icon' => 'leaderboard', 'label' => 'Leaderboard'],
                            ['route' => 'squad.builder', 'icon' => 'sports_soccer', 'label' => 'Squad Builder'],
                            ['route' => 'mini-leagues', 'icon' => 'groups', 'label' => 'Mini Leagues'],
                            ['route' => 'predictions.index', 'icon' => 'query_stats', 'label' => 'Predictions'],
                        ] as $link)
                                                    <a href="{{ route($link['route']) }}"
                                                       class="flex items-center gap-3 px-4 py-2.5 rounded-xl border border-outline-variant/15 text-on-surface-variant hover:text-[#00E676] hover:border-[#00E676]/30 transition-all text-xs font-mono font-bold uppercase tracking-wider">
                                                        <span class="material-symbols-outlined text-[16px]">{{ $link['icon'] }}</span>
                            {{ $link['label'] }}
                        </a>
                    @endforeach
                </div>
            </div>
                {{-- Sponsored banner --}}
                <livewire:campus-ad-banner />
        </div>
    </div>

    @push('ads')
        @include('partials.propeller-ad')
    @endpush
</div>