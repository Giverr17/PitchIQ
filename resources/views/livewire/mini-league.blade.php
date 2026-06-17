<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\MiniLeague;
use App\Models\FantasyTeam;
use App\Models\Tournament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

new #[Layout('layouts.app')] class extends Component {

    public $myLeagues = [];
    public ?int $viewingLeagueId = null;

    public string $newLeagueName = '';
    public ?int $newLeagueTournament = null;
    public $tournaments = [];

    public string $joinCode = '';

    public string $toast = '';
    public string $toastType = 'success';

    public function mount(): void
    {
        $this->tournaments = Tournament::whereIn('status', ['active', 'upcoming'])
            ->get(['id', 'name'])->toArray();
        $this->newLeagueTournament = $this->tournaments[0]['id'] ?? null;
        $this->loadLeagues();
    }

    public function loadLeagues(): void
    {
        $this->myLeagues = Auth::user()
            ->miniLeagues()
            ->with(['tournament', 'owner'])
            ->withCount('members')
            ->get()
            ->map(fn($l) => [
                'id'           => $l->id,
                'name'         => $l->name,
                'invite_code'  => $l->invite_code,
                'tournament'   => $l->tournament->name,
                'owner'        => $l->owner->name,
                'is_owner'     => $l->owner_id === Auth::id(),
                'member_count' => $l->members_count,
            ])->toArray();
    }

    public function createLeague(): void
    {
        $this->validate([
            'newLeagueName'       => 'required|string|max:50',
            'newLeagueTournament' => 'required|exists:tournaments,id',
        ]);

        do {
            $code = strtoupper(Str::random(6));
        } while (MiniLeague::where('invite_code', $code)->exists());

        $league = MiniLeague::create([
            'tournament_id' => $this->newLeagueTournament,
            'owner_id'      => Auth::id(),
            'name'          => $this->newLeagueName,
            'invite_code'   => $code,
        ]);

        $league->members()->attach(Auth::id());

        $this->newLeagueName = '';
        $this->loadLeagues();
        $this->flash("League created! Invite code: {$code}", 'success');
    }

    public function joinLeague(): void
    {
        $this->validate(['joinCode' => 'required|string']);

        $league = MiniLeague::where('invite_code', strtoupper(trim($this->joinCode)))->first();

        if (!$league) {
            $this->flash('No league found with that code.', 'error');
            return;
        }

        if ($league->members()->where('user_id', Auth::id())->exists()) {
            $this->flash("You're already in this league.", 'error');
            return;
        }

        $league->members()->attach(Auth::id());
        $this->joinCode = '';
        $this->loadLeagues();
        $this->flash("Joined {$league->name}!", 'success');
    }

    public function leaveLeague(int $leagueId): void
    {
        $league = MiniLeague::find($leagueId);
        if ($league) {
            $league->members()->detach(Auth::id());
            if ($this->viewingLeagueId === $leagueId) {
                $this->viewingLeagueId = null;
            }
            $this->loadLeagues();
            $this->flash('Left the league.', 'success');
        }
    }

    public function viewStandings(int $leagueId): void
    {
        $this->viewingLeagueId = $leagueId;
    }

    public function leagueStandings(): array
    {
        if (!$this->viewingLeagueId) return [];

        $league = MiniLeague::with('members')->find($this->viewingLeagueId);
        if (!$league) return [];

        $memberIds = $league->members->pluck('id')->toArray();

        return FantasyTeam::with('user')
            ->where('tournament_id', $league->tournament_id)
            ->whereIn('user_id', $memberIds)
            ->orderByDesc('total_points')
            ->get()
            ->map(fn($t) => [
                'manager'      => $t->user->name,
                'team_name'    => $t->team_name,
                'total_points' => $t->total_points,
            ])->toArray();
    }

    private function flash(string $msg, string $type = 'success'): void
    {
        $this->toast = $msg;
        $this->toastType = $type;
    }

    public function dismissToast(): void
    {
        $this->toast = '';
    }
} ?>

<div class="max-w-4xl mx-auto px-5 sm:px-8 py-10 space-y-6">

    {{-- ── Back to previous page ──────────────────────────────────────────────── --}}
    <button type="button" onclick="window.history.back()"
        class="inline-flex items-center gap-1.5 font-mono text-[11px] text-on-surface-variant/60 hover:text-[#00E676] transition-colors cursor-pointer">
        <span class="material-symbols-outlined text-[16px]">arrow_back</span>
        Back
    </button>

    {{-- ── Toast (floating: bottom-centre on mobile, top-right on desktop) ─────── --}}
    @if($toast)
        <div x-data="{ show: true }" x-show="show"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-3"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            x-init="setTimeout(() => { show = false; $wire.dismissToast() }, 5000)"
            class="fixed z-[60] inset-x-4 bottom-4 sm:inset-x-auto sm:bottom-auto sm:top-20 sm:right-4 sm:max-w-sm
                   p-4 rounded-2xl border shadow-2xl backdrop-blur-md flex items-center justify-between gap-4
                   {{ $toastType === 'success' ? 'bg-[#00E676]/15 border-[#00E676]/40 text-[#00E676]' : 'bg-red-500/15 border-red-500/40 text-red-400' }}">
            <div class="flex items-center gap-2.5">
                <span class="material-symbols-outlined text-[18px]">{{ $toastType === 'success' ? 'check_circle' : 'warning' }}</span>
                <span class="font-mono text-xs font-semibold">{{ $toast }}</span>
            </div>
            <button @click="show = false" class="opacity-60 hover:opacity-100 cursor-pointer">&times;</button>
        </div>
    @endif

    <div class="text-center mb-4">
        <h1 class="font-display font-black text-3xl text-white mb-2">Mini-<span style="color:#00E676;">Leagues</span></h1>
        <p class="font-mono text-xs text-on-surface-variant/60">Compete privately with friends.</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

        <div class="rounded-2xl border border-outline-variant/15 p-5" style="background: rgba(13,17,15,0.8);">
            <h3 class="font-display font-black text-sm uppercase tracking-wider text-white mb-4">Create League</h3>
            <div class="space-y-3">
                <input wire:model="newLeagueName" type="text" placeholder="League name"
                       class="w-full px-4 py-2.5 rounded-xl text-sm text-white placeholder-on-surface-variant/30 border border-outline-variant/20 bg-white/5 focus:outline-none focus:border-[#00E676]/50 transition-all" />
                @error('newLeagueName') <p class="text-red-400 text-xs font-mono">{{ $message }}</p> @enderror

                <select wire:model="newLeagueTournament"
                        class="w-full px-4 py-2.5 rounded-xl text-sm text-white border border-outline-variant/20 bg-[#0d110f] focus:outline-none focus:border-[#00E676]/50 transition-all appearance-none cursor-pointer">
                    @foreach($tournaments as $t)
                        <option value="{{ $t['id'] }}">{{ $t['name'] }}</option>
                    @endforeach
                </select>

                <button wire:click="createLeague"
                        class="w-full py-2.5 rounded-xl text-xs font-mono font-bold uppercase tracking-wider text-black cursor-pointer"
                        style="background: linear-gradient(135deg, #00E676 0%, #00b359 100%);">
                    Create
                </button>
            </div>
        </div>

        <div class="rounded-2xl border border-outline-variant/15 p-5" style="background: rgba(13,17,15,0.8);">
            <h3 class="font-display font-black text-sm uppercase tracking-wider text-white mb-4">Join League</h3>
            <div class="space-y-3">
                <input wire:model="joinCode" type="text" placeholder="Enter invite code"
                       class="w-full px-4 py-2.5 rounded-xl text-sm text-white placeholder-on-surface-variant/30 border border-outline-variant/20 bg-white/5 focus:outline-none focus:border-[#00E676]/50 transition-all uppercase" />
                @error('joinCode') <p class="text-red-400 text-xs font-mono">{{ $message }}</p> @enderror

                <button wire:click="joinLeague"
                        class="w-full py-2.5 rounded-xl text-xs font-mono font-bold uppercase tracking-wider text-white border border-outline-variant/20 hover:border-[#00E676]/40 cursor-pointer transition-all">
                    Join
                </button>
            </div>
        </div>
    </div>

    <div class="space-y-3">
        <h3 class="font-display font-black text-sm uppercase tracking-wider text-white">My Leagues</h3>

        @forelse($myLeagues as $league)
            <div class="rounded-xl border border-outline-variant/15 p-4" style="background: rgba(13,17,15,0.8);">
                <div class="flex items-center justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-white">{{ $league['name'] }}</span>
                            @if($league['is_owner'])
                                <span class="font-mono text-[9px] px-1.5 py-0.5 rounded" style="background:rgba(0,230,118,0.1);color:#00E676;">OWNER</span>
                            @endif
                        </div>
                        <p class="font-mono text-[10px] text-on-surface-variant/50 mt-0.5">
                            {{ $league['tournament'] }} · {{ $league['member_count'] }} members
                        </p>
                        <p class="font-mono text-[10px] text-on-surface-variant/40 mt-1">
                            Code: <span class="text-[#00E676] font-bold">{{ $league['invite_code'] }}</span>
                        </p>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <button wire:click="viewStandings({{ $league['id'] }})"
                                class="px-3 py-1.5 rounded-lg text-[10px] font-mono font-bold uppercase tracking-wider border transition-all cursor-pointer"
                                style="background:rgba(0,230,118,0.08);color:#00E676;border-color:rgba(0,230,118,0.25);">
                            Standings
                        </button>
                        <button wire:click="leaveLeague({{ $league['id'] }})"
                                wire:confirm="Leave this league?"
                                class="px-3 py-1.5 rounded-lg text-[10px] font-mono font-bold uppercase tracking-wider text-on-surface-variant/60 border border-outline-variant/20 hover:text-red-400 hover:border-red-400/40 transition-all cursor-pointer">
                            Leave
                        </button>
                    </div>
                </div>

                @if($viewingLeagueId === $league['id'])
                    <div class="mt-4 pt-4 border-t border-outline-variant/10">
                        @php $standings = $this->leagueStandings(); @endphp
                        <table class="w-full text-left">
                            <thead>
                                <tr class="font-mono text-[10px] uppercase tracking-wider text-on-surface-variant/50">
                                    <th class="pb-2 w-10">#</th>
                                    <th class="pb-2">Manager</th>
                                    <th class="pb-2 text-right">Points</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm">
                                @forelse($standings as $i => $row)
                                    <tr class="border-t border-outline-variant/5">
                                        <td class="py-2 font-mono font-bold {{ $i < 3 ? '' : 'text-on-surface-variant/50' }}"
                                            style="{{ $i < 3 ? 'color:#00E676;' : '' }}">{{ $i + 1 }}</td>
                                        <td class="py-2">
                                            <span class="text-white font-bold">{{ $row['manager'] }}</span>
                                            <span class="text-on-surface-variant/40 text-xs ml-1">{{ $row['team_name'] }}</span>
                                        </td>
                                        <td class="py-2 text-right font-mono font-bold" style="color:#00E676;">{{ $row['total_points'] }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="py-4 text-center font-mono text-xs text-on-surface-variant/40">No squads built yet in this league.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @empty
            <div class="rounded-xl border border-outline-variant/15 p-8 text-center" style="background: rgba(13,17,15,0.8);">
                <p class="font-mono text-xs text-on-surface-variant/40">You're not in any leagues yet. Create one or join with a code.</p>
            </div>
        @endforelse
    </div>
</div>