<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Tournament;
use App\Models\Fixture;
use App\Models\FantasyTeam;
use App\Models\AirtimePayout;
use App\Models\User;
use App\Jobs\SendAirtimePayoutJob;
use Illuminate\Support\Facades\DB;

new #[Layout('layouts.admin')] class extends Component {

    public $tournaments = [];
    public ?int $tournamentId = null;
    public string $scope = 'matchday';     // 'matchday' | 'tournament'
    public ?int $matchday = 1;

    // Prize amounts (naira) for ranks 1, 2, 3
    public int $prize1 = 500;
    public int $prize2 = 300;
    public int $prize3 = 200;

    public array $preview = [];      // computed top 3
    public string $message = '';
    public string $messageType = 'success';
    public $recentPayouts = [];

    // ─── Matchday control ─────────────────────────────────────────────
    public ?int $newActiveMatchday = null;
    public int $currentActiveMatchday = 1;

    public function mount(): void
    {
        $this->tournaments = Tournament::orderByDesc('id')->get(['id', 'name'])->toArray();
        $this->tournamentId = $this->tournaments[0]['id'] ?? null;
        $t = Tournament::find($this->tournamentId);
        $this->currentActiveMatchday = $t?->active_matchday ?? 1;
        $this->newActiveMatchday = $this->currentActiveMatchday;
        $this->loadRecent();
    }

    public function loadRecent(): void
    {
        $this->recentPayouts = AirtimePayout::with(['user', 'tournament'])
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn($p) => [
                'user' => $p->user->name,
                'phone' => $p->phone,
                'amount' => $p->amount,
                'rank' => $p->rank,
                'scope' => $p->scope,
                'matchday' => $p->matchday,
                'tournament' => $p->tournament?->name ?? '—',
                'status' => $p->status,
                'reference' => $p->provider_reference,
                'when' => $p->created_at->format('d M H:i'),
            ])->toArray();
    }

    public function updatedTournamentId(): void
    {
        $t = Tournament::find($this->tournamentId);
        $this->currentActiveMatchday = $t?->active_matchday ?? 1;
        $this->newActiveMatchday = $this->currentActiveMatchday;
        $this->preview = [];
    }

    public function setActiveMatchday(): void
    {
        $this->validate(['newActiveMatchday' => 'required|integer|min:1']);

        $tournament = Tournament::find($this->tournamentId);
        if (!$tournament) {
            $this->flash('No tournament selected.', 'error');
            return;
        }

        $tournament->update(['active_matchday' => $this->newActiveMatchday]);
        $this->currentActiveMatchday = $this->newActiveMatchday;
        $this->flash("Active matchday set to MD{$this->newActiveMatchday}.", 'success');
    }

    public function advanceMatchday(): void
    {
        $tournament = Tournament::find($this->tournamentId);
        if (!$tournament) {
            $this->flash('No tournament selected.', 'error');
            return;
        }

        $tournament->increment('active_matchday');
        $this->currentActiveMatchday = $tournament->fresh()->active_matchday;
        $this->newActiveMatchday = $this->currentActiveMatchday;
        $this->flash("Advanced to MD{$this->currentActiveMatchday}.", 'success');
    }

    // Build the top-3 preview WITHOUT paying
    public function loadPreview(): void
    {
        $this->preview = [];
        if (!$this->tournamentId)
            return;

        $winners = $this->topThree();

        $prizes = [1 => $this->prize1, 2 => $this->prize2, 3 => $this->prize3];
        foreach ($winners as $i => $w) {
            $rank = $i + 1;
            $this->preview[] = [
                'rank' => $rank,
                'user_id' => $w['user_id'],
                'manager' => $w['manager'],
                'phone' => $w['phone'] ?: 'NO PHONE SET',
                'points' => $w['points'],
                'amount' => $prizes[$rank] ?? 0,
            ];
        }
    }

    // Determine the top 3 by fantasy points for the chosen scope
    private function topThree(): array
    {
        // ── Matchday scope ───────────────────────────────────────────────────────
        // Rank the single team each user entered for this specific matchday.
        if ($this->scope === 'matchday') {
            return FantasyTeam::with('user')
                ->where('tournament_id', $this->tournamentId)
                ->where('total_points', '>', 0)
                ->whereHas('fixture', fn($q) => $q->where('matchday', $this->matchday))
                ->orderByDesc('total_points')
                ->limit(3)
                ->get()
                ->unique('user_id')   // harmless safety against data anomalies
                ->values()
                ->map(fn($t) => [
                    'user_id' => $t->user_id,
                    'manager' => $t->user->name,
                    'phone' => $t->user->phone,
                    'points' => $t->total_points,
                ])->toArray();
        }

        // ── Tournament (overall) scope ───────────────────────────────────────────
        // Sum each user's total_points across all their matchday teams in the
        // tournament, then take the top 3.
        return FantasyTeam::join('users', 'fantasy_teams.user_id', '=', 'users.id')
            ->where('fantasy_teams.tournament_id', $this->tournamentId)
            ->groupBy('fantasy_teams.user_id', 'users.name', 'users.phone')
            ->select(
                'fantasy_teams.user_id',
                'users.name  as manager',
                'users.phone as phone',
                DB::raw('SUM(fantasy_teams.total_points) as total_points')
            )
            ->havingRaw('SUM(fantasy_teams.total_points) > 0')
            ->orderByDesc('total_points')
            ->limit(3)
            ->get()
            ->map(fn($row) => [
                'user_id' => $row->user_id,
                'manager' => $row->manager,
                'phone' => $row->phone,
                'points' => (int) $row->total_points,
            ])->toArray();
    }

    public function settleAndPay(): void
    {
        if (!$this->tournamentId) {
            $this->flash('Select a tournament first.', 'error');
            return;
        }
        if ($this->scope === 'matchday') {
            $hasScored = Fixture::where('tournament_id', $this->tournamentId)
                ->where('matchday', $this->matchday)
                ->where('status', 'completed')
                ->exists();

            if (!$hasScored) {
                $this->flash("MD{$this->matchday} has no completed fixtures yet — nothing to settle.", 'error');
                return;
            }
        }

        $winners = $this->topThree();
        if (empty($winners)) {
            $this->flash('No eligible winners (no one has scored points yet).', 'error');
            return;
        }

        $prizes = [1 => $this->prize1, 2 => $this->prize2, 3 => $this->prize3];
        $queued = 0;
        $skipped = 0;

        foreach ($winners as $i => $w) {
            $rank = $i + 1;
            $amount = $prizes[$rank] ?? 0;

            if ($amount <= 0)
                continue;           // ₦0 prize = skip (bragging-rights mode)

            if (($w['points'] ?? 0) <= 0) {       // ← never pay a 0-point "winner"
                $skipped++;
                continue;
            }
            $user = User::find($w['user_id']);
            if (!$user || !$user->phone) {
                $skipped++;
                continue;                          // can't pay without a phone
            }

            // Idempotency: skip if this user already has a settled or in-flight
            // payout for this exact event+scope. 'failed' rows are intentionally
            // NOT counted, so re-clicking Settle retries the ones that failed.
            $exists = AirtimePayout::where('tournament_id', $this->tournamentId)
                ->where('matchday', $this->scope === 'matchday' ? $this->matchday : null)
                ->where('scope', $this->scope)
                ->where('user_id', $user->id)
                ->whereIn('status', ['pending', 'processing', 'success'])
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            // Create a pending record first (audit trail before money moves)
            $payout = AirtimePayout::create([
                'user_id' => $user->id,
                'tournament_id' => $this->tournamentId,
                'matchday' => $this->scope === 'matchday' ? $this->matchday : null,
                'scope' => $this->scope,
                'rank' => $rank,
                'phone' => $user->phone,
                'amount' => $amount,
                'status' => 'pending',
            ]);

            // Hand the provider HTTP call off to the queue so this admin request
            // returns immediately instead of blocking on up to ~30s per winner.
            // (On QUEUE_CONNECTION=sync it still runs inline — see deploy note.)
            SendAirtimePayoutJob::dispatch($payout->id);
            $queued++;
            
        }
        

        $this->loadRecent();
        $this->loadPreview();

        $this->flash(
            "Settling {$queued} payout(s), {$skipped} skipped. Airtime is sent in the background.",
            $queued > 0 ? 'success' : 'error'
        );
    }

    public function unpaidMatchdays(): array
    {
        if (!$this->tournamentId)
            return [];

        $completedMatchdays = Fixture::where('tournament_id', $this->tournamentId)
            ->where('status', 'completed')
            ->distinct()
            ->orderBy('matchday')
            ->pluck('matchday')
            ->all();

        return array_values(array_filter(
            $completedMatchdays,
            fn(int $md) =>
            !AirtimePayout::where('tournament_id', $this->tournamentId)
                ->where('matchday', $md)
                ->where('scope', 'matchday')
                ->where('status', 'success')
                ->exists()
        ));
    }

    public function jumpToMatchday(int $md): void
    {
        $this->scope = 'matchday';
        $this->matchday = $md;
        $this->loadPreview();
    }

    private function flash(string $msg, string $type): void
    {
        $this->message = $msg;
        $this->messageType = $type;
    }
} ?>

<div class="space-y-6">

    {{-- Header --}}
    <div>
        <h2 class="font-display font-black text-2xl text-on-surface uppercase tracking-tight">Payouts</h2>
        <p class="text-on-surface-variant/60 text-xs mt-1 font-mono">Settle a matchday or tournament and pay the top 3
            in airtime.</p>
    </div>

    {{-- Message --}}
    @if($message)
        <div class="p-3 rounded-xl border flex items-center gap-2.5
                                {{ $messageType === 'success' ? 'bg-[#00E676]/10 border-[#00E676]/30 text-[#00E676]' : 'bg-red-500/10 border-red-500/30 text-red-400' }}"
            x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)">
            <span
                class="material-symbols-outlined text-[16px]">{{ $messageType === 'success' ? 'check_circle' : 'warning' }}</span>
            <span class="font-mono text-xs font-semibold">{{ $message }}</span>
        </div>
    @endif

    {{-- Matchday Control --}}
    <div class="rounded-2xl border border-outline-variant/15 p-6 space-y-4" style="background: rgba(13,17,15,0.8);">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h3 class="font-display font-black text-sm uppercase tracking-wider text-white">Matchday Control</h3>
                <p class="font-mono text-[10px] text-on-surface-variant/50 mt-0.5">Open &amp; close fixture entries by
                    setting the active matchday.</p>
            </div>
            <div class="flex-shrink-0 text-center px-5 py-2.5 rounded-xl border border-[#00E676]/20"
                style="background: rgba(0,230,118,0.06);">
                <span
                    class="block font-mono text-[9px] uppercase tracking-widest text-on-surface-variant/50">Active</span>
                <span class="block font-black text-2xl font-mono leading-none mt-0.5"
                    style="color:#00E676;">MD{{ $currentActiveMatchday }}</span>
            </div>
        </div>
        <div class="flex flex-col sm:flex-row gap-3 items-start sm:items-end">
            <div class="flex-1 min-w-0">
                <label class="block text-xs font-mono text-on-surface-variant uppercase tracking-wider mb-1.5">Set to
                    Matchday</label>
                <input wire:model="newActiveMatchday" type="number" min="1"
                    class="w-full px-4 py-2.5 rounded-xl text-sm text-white border border-outline-variant/20 bg-white/5 focus:outline-none focus:border-[#00E676]/50" />
                @error('newActiveMatchday')
                    <p class="font-mono text-[10px] text-red-400 mt-1">{{ $message }}</p>
                @enderror
            </div>
            <button wire:click="setActiveMatchday"
                class="px-5 py-2.5 rounded-xl text-xs font-mono font-bold uppercase tracking-wider text-white border border-outline-variant/20 hover:border-[#00E676]/40 cursor-pointer transition-all flex-shrink-0">
                Set Matchday
            </button>
            <button wire:click="advanceMatchday"
                wire:confirm="This opens the next matchday for entry. You can still settle and pay earlier matchdays anytime. Continue?"
                class="px-5 py-2.5 rounded-xl text-xs font-mono font-bold uppercase tracking-wider text-black cursor-pointer transition-all flex-shrink-0"
                style="background: linear-gradient(135deg, #00E676 0%, #00b359 100%);">
                Advance → MD{{ $currentActiveMatchday + 1 }}
            </button>
        </div>
    </div>

    {{-- Unpaid matchdays reminder --}}
    @php $unpaid = $this->unpaidMatchdays(); @endphp
    @if(count($unpaid) > 0)
        <div class="rounded-2xl border border-amber-500/20 p-4" style="background: rgba(13,17,15,0.8);">
            <div class="flex items-start gap-3">
                <span class="material-symbols-outlined text-amber-400 flex-shrink-0 mt-0.5"
                    style="font-size:18px;">warning</span>
                <div class="flex-1 min-w-0 space-y-2">
                    <div>
                        <span class="font-mono text-[10px] font-bold uppercase tracking-widest text-amber-400">Matchdays
                            awaiting payout</span>
                        <p class="font-mono text-[10px] text-on-surface-variant/50 mt-0.5">You can settle these anytime —
                            advancing rounds doesn't block earlier payouts.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @foreach($unpaid as $md)
                            <button wire:click="jumpToMatchday({{ $md }})"
                                class="px-3 py-1.5 rounded-lg border border-amber-500/30 font-mono text-[10px] font-bold uppercase tracking-wider text-amber-400 hover:bg-amber-500/10 cursor-pointer transition-all">
                                MD{{ $md }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @else
        <p class="font-mono text-[10px] text-on-surface-variant/25 flex items-center gap-1.5 px-1">
            <span class="material-symbols-outlined" style="font-size:12px;">check_circle</span>
            All scored matchdays paid
        </p>
    @endif

    {{-- Settle form --}}
    <div class="rounded-2xl border border-outline-variant/15 p-6 space-y-5" style="background: rgba(13,17,15,0.8);">

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            {{-- Tournament --}}
            <div>
                <label
                    class="block text-xs font-mono text-on-surface-variant uppercase tracking-wider mb-1.5">Tournament</label>
                <select wire:model.live="tournamentId"
                    class="w-full px-4 py-2.5 rounded-xl text-sm text-white border border-outline-variant/20 bg-[#0d110f] focus:outline-none focus:border-[#00E676]/50 appearance-none cursor-pointer">
                    @foreach($tournaments as $t)
                        <option value="{{ $t['id'] }}">{{ $t['name'] }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Scope --}}
            <div>
                <label
                    class="block text-xs font-mono text-on-surface-variant uppercase tracking-wider mb-1.5">Scope</label>
                <select wire:model.live="scope"
                    class="w-full px-4 py-2.5 rounded-xl text-sm text-white border border-outline-variant/20 bg-[#0d110f] focus:outline-none focus:border-[#00E676]/50 appearance-none cursor-pointer">
                    <option value="matchday">Per Matchday</option>
                    <option value="tournament">Whole Tournament</option>
                </select>
            </div>

            {{-- Matchday (only if scope=matchday) --}}
            <div>
                <label
                    class="block text-xs font-mono text-on-surface-variant uppercase tracking-wider mb-1.5">Matchday</label>
                <input wire:model="matchday" type="number" min="1" @if($scope !== 'matchday') disabled @endif
                    class="w-full px-4 py-2.5 rounded-xl text-sm text-white border border-outline-variant/20 bg-white/5 focus:outline-none focus:border-[#00E676]/50 disabled:opacity-40" />
            </div>
        </div>

        {{-- Prizes --}}
        <div class="grid grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-mono text-on-surface-variant uppercase tracking-wider mb-1.5">🥇 1st
                    (₦)</label>
                <input wire:model="prize1" type="number" min="0"
                    class="w-full px-4 py-2.5 rounded-xl text-sm text-white border border-outline-variant/20 bg-white/5 focus:outline-none focus:border-[#00E676]/50" />
            </div>
            <div>
                <label class="block text-xs font-mono text-on-surface-variant uppercase tracking-wider mb-1.5">🥈 2nd
                    (₦)</label>
                <input wire:model="prize2" type="number" min="0"
                    class="w-full px-4 py-2.5 rounded-xl text-sm text-white border border-outline-variant/20 bg-white/5 focus:outline-none focus:border-[#00E676]/50" />
            </div>
            <div>
                <label class="block text-xs font-mono text-on-surface-variant uppercase tracking-wider mb-1.5">🥉 3rd
                    (₦)</label>
                <input wire:model="prize3" type="number" min="0"
                    class="w-full px-4 py-2.5 rounded-xl text-sm text-white border border-outline-variant/20 bg-white/5 focus:outline-none focus:border-[#00E676]/50" />
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button wire:click="loadPreview"
                class="px-5 py-2.5 rounded-xl text-xs font-mono font-bold uppercase tracking-wider text-white border border-outline-variant/20 hover:border-[#00E676]/40 cursor-pointer transition-all">
                Preview Winners
            </button>
            <button wire:click="settleAndPay" wire:confirm="Pay the top 3 now? This sends airtime and cannot be undone."
                wire:loading.attr="disabled"
                class="px-5 py-2.5 rounded-xl text-xs font-mono font-bold uppercase tracking-wider text-black cursor-pointer disabled:opacity-50"
                style="background: linear-gradient(135deg, #00E676 0%, #00b359 100%);">
                <span wire:loading.remove wire:target="settleAndPay">Settle &amp; Pay</span>
                <span wire:loading wire:target="settleAndPay">Processing...</span>
            </button>
        </div>

        {{-- Preview --}}
        @if(count($preview) > 0)
            <div class="border-t border-outline-variant/10 pt-4 space-y-2">
                <p class="font-mono text-[10px] text-on-surface-variant/50 uppercase tracking-widest">Preview (not yet paid)
                </p>
                @foreach($preview as $p)
                    <div class="flex items-center justify-between px-4 py-2.5 rounded-xl border border-outline-variant/15"
                        style="background: rgba(255,255,255,0.02);">
                        <div class="flex items-center gap-3">
                            <span class="font-mono font-bold" style="color:#00E676;">#{{ $p['rank'] }}</span>
                            <span class="text-white font-bold text-sm">{{ $p['manager'] }}</span>
                            <span
                                class="font-mono text-[10px] {{ $p['phone'] === 'NO PHONE SET' ? 'text-red-400' : 'text-on-surface-variant/50' }}">{{ $p['phone'] }}</span>
                        </div>
                        <div class="flex items-center gap-4">
                            <span class="font-mono text-xs text-on-surface-variant/50">{{ $p['points'] }} pts</span>
                            <span class="font-mono text-sm font-bold" style="color:#00E676;">₦{{ $p['amount'] }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Recent payouts --}}
    <div class="rounded-2xl border border-outline-variant/15 overflow-hidden" style="background: rgba(13,17,15,0.8);">
        <div class="px-5 py-3 border-b border-outline-variant/10">
            <h3 class="font-display font-black text-sm uppercase tracking-wider text-white">Recent Payouts</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm min-w-[540px]">
                <thead>
                    <tr
                        class="font-mono text-[10px] uppercase tracking-wider text-on-surface-variant/50 border-b border-outline-variant/10">
                        <th class="py-2.5 px-5">Winner</th>
                        <th class="py-2.5 px-5">Scope</th>
                        <th class="py-2.5 px-5 text-right">Amount</th>
                        <th class="py-2.5 px-5 text-center">Status</th>
                        <th class="py-2.5 px-5">When</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant/8">
                    @forelse($recentPayouts as $p)
                        <tr>
                            <td class="py-3 px-5">
                                <span class="text-white font-bold">{{ $p['user'] }}</span>
                                <span class="font-mono text-[10px] text-on-surface-variant/40 ml-1">#{{ $p['rank'] }} ·
                                    {{ $p['phone'] }}</span>
                            </td>
                            <td class="py-3 px-5 font-mono text-[10px] text-on-surface-variant/60">
                                {{ $p['scope'] === 'matchday' ? 'MD' . $p['matchday'] : 'Tournament' }}
                                <span class="block text-on-surface-variant/30">{{ $p['tournament'] }}</span>
                            </td>
                            <td class="py-3 px-5 text-right font-mono font-bold" style="color:#00E676;">₦{{ $p['amount'] }}
                            </td>
                            <td class="py-3 px-5 text-center">
                                <span
                                    class="px-2 py-0.5 rounded-full font-mono text-[9px] font-bold uppercase tracking-widest
                                            {{ $p['status'] === 'success' ? 'text-[#00E676]' : ($p['status'] === 'failed' ? 'text-red-400' : 'text-amber-400') }}">
                                    {{ $p['status'] }}
                                </span>
                            </td>
                            <td class="py-3 px-5 font-mono text-[10px] text-on-surface-variant/40">{{ $p['when'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-10 text-center font-mono text-xs text-on-surface-variant/40">No
                                payouts yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>