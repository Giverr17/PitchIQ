<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Computed;
use App\Models\FantasyTeam;
use App\Models\Prediction;
use App\Models\Tournament;
use App\Models\Referral;
use Illuminate\Support\Facades\Auth;

new #[Layout('layouts.app'), Lazy] class extends Component {

    public ?int $tournamentId = null;
    public $tournaments = [];

    // Token / referral stats (tournament-independent)
    public int $tokens = 0;
    public int $referralsJoined = 0;
    public int $referralsRewarded = 0;
    public int $referralTokensEarned = 0;

    public function placeholder(): string
    {
        return <<<'HTML'
        <div class="max-w-4xl mx-auto px-5 sm:px-8 py-10">
            <div class="h-8 shimmer rounded-xl w-44 mb-8"></div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
                <div class="h-24 shimmer rounded-2xl"></div>
                <div class="h-24 shimmer rounded-2xl"></div>
                <div class="h-24 shimmer rounded-2xl"></div>
                <div class="h-24 shimmer rounded-2xl"></div>
            </div>
            <div class="h-48 shimmer rounded-2xl"></div>
        </div>
        HTML;
    }

    public function mount(): void
    {
        $user = Auth::user();

        $this->tournaments = Tournament::orderByRaw("FIELD(status, 'active', 'upcoming', 'completed')")
            ->get(['id', 'name'])
            ->toArray();

        $this->tournamentId = Tournament::where('status', 'active')->value('id')
            ?? ($this->tournaments[0]['id'] ?? null);

        // Token + referral summary (mirrors the dashboard)
        $this->tokens = $user->tokens;
        $this->referralsJoined = $user->referredUsers()->count();
        $this->referralsRewarded = Referral::where('referrer_id', $user->id)
            ->where('status', 'completed')
            ->count();
        $this->referralTokensEarned = $this->referralsRewarded * 20;
    }

    // All tournament-scoped numbers in one place. Rank is computed exactly like
    // the dashboard: combined fantasy + verified-prediction points per user.
    #[Computed]
    public function data(): array
    {
        $userId = Auth::id();

        if (!$this->tournamentId) {
            return ['hasData' => false];
        }

        $tid = $this->tournamentId;

        $allFantasy = FantasyTeam::where('tournament_id', $tid)
            ->selectRaw('user_id, SUM(total_points) as ft_sum')
            ->groupBy('user_id')
            ->pluck('ft_sum', 'user_id');

        $allPreds = Prediction::whereHas('fixture', fn($q) => $q->where('tournament_id', $tid))
            ->whereNotNull('verified_at')
            ->selectRaw('user_id, SUM(points_earned) as pred_sum')
            ->groupBy('user_id')
            ->pluck('pred_sum', 'user_id');

        $allUids = $allFantasy->keys()->merge($allPreds->keys())->unique();
        $allTotals = $allUids->mapWithKeys(fn($uid) => [
            $uid => ($allFantasy[$uid] ?? 0) + ($allPreds[$uid] ?? 0),
        ]);

        $myFantasy = (int) ($allFantasy[$userId] ?? 0);
        $myPred    = (int) ($allPreds[$userId] ?? 0);
        $myTotal   = $myFantasy + $myPred;

        // Prediction accuracy for this user in this tournament
        $base = Prediction::where('user_id', $userId)
            ->whereHas('fixture', fn($q) => $q->where('tournament_id', $tid));
        $predsMade     = (clone $base)->count();
        $predsVerified = (clone $base)->whereNotNull('verified_at')->count();
        $predsCorrect  = (clone $base)->where('points_earned', '>', 0)->count();

        // Per-matchday fantasy breakdown (one team per fixture entered)
        $matchdays = FantasyTeam::with('fixture')
            ->where('user_id', $userId)
            ->where('tournament_id', $tid)
            ->get()
            ->map(fn($t) => [
                'matchday'  => $t->fixture?->matchday,
                'team_name' => $t->team_name,
                'points'    => (int) $t->total_points,
            ])
            ->sortBy('matchday')
            ->values()
            ->toArray();

        return [
            'hasData'       => $allTotals->isNotEmpty() || $predsMade > 0 || count($matchdays) > 0,
            'myFantasy'     => $myFantasy,
            'myPred'        => $myPred,
            'myTotal'       => $myTotal,
            'rank'          => $allTotals->filter(fn($t) => $t > $myTotal)->count() + 1,
            'managers'      => $allTotals->count(),
            'predsMade'     => $predsMade,
            'predsVerified' => $predsVerified,
            'predsCorrect'  => $predsCorrect,
            'matchdays'     => $matchdays,
        ];
    }
}; ?>

<div class="max-w-4xl mx-auto px-5 sm:px-8 py-10 space-y-6">

    {{-- Back --}}
    <button type="button" onclick="window.history.back()"
        class="inline-flex items-center gap-1.5 font-mono text-[11px] text-on-surface-variant/60 hover:text-[#00E676] transition-colors cursor-pointer">
        <span class="material-symbols-outlined text-[16px]">arrow_back</span>
        Back
    </button>

    {{-- Header --}}
    <div class="text-center mb-2">
        <h1 class="font-display font-black text-3xl sm:text-4xl text-white tracking-tight mb-1">
            My <span style="color:#00E676;">Stats</span>
        </h1>
        <p class="font-mono text-[10px] uppercase tracking-widest text-on-surface-variant/60">{{ Auth::user()->name }}</p>
    </div>

    {{-- Tournament selector --}}
    @if(count($tournaments) > 0)
        <div class="flex justify-center">
            <select wire:model.live="tournamentId"
                class="px-4 py-2.5 rounded-xl text-sm text-white border border-outline-variant/20 bg-[#0d110f] focus:outline-none focus:border-[#00E676]/50 transition-all appearance-none cursor-pointer">
                @foreach($tournaments as $t)
                    <option value="{{ $t['id'] }}">{{ $t['name'] }}</option>
                @endforeach
            </select>
        </div>
    @endif

    @php $d = $this->data; @endphp

    {{-- Headline cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="rounded-2xl border border-[#00E676]/25 p-4 text-center" style="background:rgba(0,230,118,0.04);">
            <span class="block font-mono font-black text-3xl" style="color:#00E676;">{{ $d['myTotal'] ?? 0 }}</span>
            <span class="font-mono text-[9px] uppercase tracking-widest text-on-surface-variant/50 mt-1 block">Total Points</span>
        </div>
        <div class="rounded-2xl border border-outline-variant/15 p-4 text-center" style="background:rgba(13,17,15,0.8);">
            <span class="block font-mono font-black text-3xl text-white">
                @if(($d['managers'] ?? 0) > 0)#{{ $d['rank'] }}@else —@endif
            </span>
            <span class="font-mono text-[9px] uppercase tracking-widest text-on-surface-variant/50 mt-1 block">
                Rank @if(($d['managers'] ?? 0) > 0)<span class="text-on-surface-variant/30">/ {{ $d['managers'] }}</span>@endif
            </span>
        </div>
        <div class="rounded-2xl border border-outline-variant/15 p-4 text-center" style="background:rgba(13,17,15,0.8);">
            <span class="block font-mono font-black text-3xl text-white">{{ $d['myFantasy'] ?? 0 }}</span>
            <span class="font-mono text-[9px] uppercase tracking-widest text-on-surface-variant/50 mt-1 block">Fantasy Pts</span>
        </div>
        <div class="rounded-2xl border border-outline-variant/15 p-4 text-center" style="background:rgba(13,17,15,0.8);">
            <span class="block font-mono font-black text-3xl text-white">{{ $d['myPred'] ?? 0 }}</span>
            <span class="font-mono text-[9px] uppercase tracking-widest text-on-surface-variant/50 mt-1 block">Prediction Pts</span>
        </div>
    </div>

    {{-- Predictions accuracy + tokens/referrals --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        {{-- Predictions --}}
        <div class="rounded-2xl border border-outline-variant/15 p-5" style="background:rgba(13,17,15,0.8);">
            <div class="flex items-center gap-2 mb-3">
                <span class="material-symbols-outlined text-[18px]" style="color:#00E676;">query_stats</span>
                <p class="font-mono text-[11px] font-bold uppercase tracking-widest text-on-surface-variant/70">Predictions</p>
            </div>
            <div class="grid grid-cols-3 gap-2 text-center">
                <div>
                    <span class="block font-mono font-black text-xl text-white">{{ $d['predsMade'] ?? 0 }}</span>
                    <span class="font-mono text-[8px] uppercase tracking-wider text-on-surface-variant/50">Made</span>
                </div>
                <div>
                    <span class="block font-mono font-black text-xl text-white">{{ $d['predsVerified'] ?? 0 }}</span>
                    <span class="font-mono text-[8px] uppercase tracking-wider text-on-surface-variant/50">Verified</span>
                </div>
                <div>
                    <span class="block font-mono font-black text-xl" style="color:#00E676;">{{ $d['predsCorrect'] ?? 0 }}</span>
                    <span class="font-mono text-[8px] uppercase tracking-wider text-on-surface-variant/50">Correct</span>
                </div>
            </div>
        </div>

        {{-- Tokens & referrals --}}
        <div class="rounded-2xl border border-outline-variant/15 p-5" style="background:rgba(13,17,15,0.8);">
            <div class="flex items-center gap-2 mb-3">
                <span class="material-symbols-outlined text-[18px]" style="color:#00E676;">toll</span>
                <p class="font-mono text-[11px] font-bold uppercase tracking-widest text-on-surface-variant/70">Tokens & Referrals</p>
            </div>
            <div class="grid grid-cols-3 gap-2 text-center">
                <div>
                    <span class="block font-mono font-black text-xl" style="color:#00E676;">{{ $tokens }}</span>
                    <span class="font-mono text-[8px] uppercase tracking-wider text-on-surface-variant/50">Balance</span>
                </div>
                <div>
                    <span class="block font-mono font-black text-xl text-white">{{ $referralsJoined }}</span>
                    <span class="font-mono text-[8px] uppercase tracking-wider text-on-surface-variant/50">Referred</span>
                </div>
                <div>
                    <span class="block font-mono font-black text-xl text-white">+{{ $referralTokensEarned }}</span>
                    <span class="font-mono text-[8px] uppercase tracking-wider text-on-surface-variant/50">Earned</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Per-matchday fantasy breakdown --}}
    <div class="rounded-2xl border border-outline-variant/15 overflow-hidden" style="background: rgba(13,17,15,0.8);">
        <div class="px-5 py-3.5 border-b border-outline-variant/10" style="background:rgba(255,255,255,0.02);">
            <p class="font-mono text-[11px] font-bold uppercase tracking-widest text-on-surface-variant/70">Your Fantasy Matchdays</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[360px]">
                <thead>
                    <tr class="border-b border-outline-variant/15 font-mono text-xs uppercase tracking-wider text-on-surface-variant/60">
                        <th class="py-3 px-5 w-20">MD</th>
                        <th class="py-3 px-5">Squad</th>
                        <th class="py-3 px-5 text-right">Points</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant/10 text-sm">
                    @forelse($d['matchdays'] ?? [] as $row)
                        <tr class="hover:bg-white/[0.02] transition-colors">
                            <td class="py-3.5 px-5">
                                <span class="font-mono text-[10px] font-bold text-on-surface-variant bg-white/5 px-2 py-0.5 rounded border border-outline-variant/20">
                                    MD{{ $row['matchday'] ?? '?' }}
                                </span>
                            </td>
                            <td class="py-3.5 px-5 font-bold text-white">{{ $row['team_name'] }}</td>
                            <td class="py-3.5 px-5 text-right font-mono font-bold" style="color:#00E676;">{{ $row['points'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="py-12 text-center font-mono text-xs text-on-surface-variant/40">
                                You haven’t entered a fantasy squad in this tournament yet.
                                <a href="{{ route('squad.builder') }}" class="text-[#00E676] hover:underline ml-1">Build one →</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @push('ads')
        @include('partials.propeller-ad')
    @endpush
</div>
