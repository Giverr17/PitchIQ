<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use App\Models\Player;
use App\Models\Team;
use App\Enums\PlayerPosition;

new #[Layout('layouts.admin')] class extends Component {

    public $teams = [];

    public string $search = '';
    public string $positionFilter = '';

    // Form fields
    public int|string $team_id = '';
    public string $name = '';
    public string $position = '';
    public string $number = '';
    public string $fantasy_price = '50';
    public int|string $aiTeamId = '';
    public string $aiInstruction = '';
    public array $aiPlayers = [];
    public array $aiWarnings = [];
    public string $aiMessage = '';

    // State
    public bool $showModal = false;
    public ?int $editingId = null;

    public function mount(): void
    {
        $this->teams = Team::with('tournament')
            ->orderBy('name')
            ->get(['id', 'name', 'tournament_id', 'colour'])
            ->toArray();
    }

    #[Computed]
    public function players(): array
    {
        return Player::with('team')
            ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($this->positionFilter, fn($q) => $q->where('position', $this->positionFilter))
            ->latest()
            ->get()
            ->toArray();
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $player = Player::findOrFail($id);
        $this->editingId = $id;
        $this->team_id = $player->team_id;
        $this->name = $player->name;
        $this->position = $player->position->value;
        $this->number = (string) ($player->number ?? '');
        $this->fantasy_price = (string) $player->fantasy_price;
        $this->showModal = true;
    }

    public function generateRoster(\App\Services\AiService $ai): void
    {
        if (!$this->aiTeamId) {
            $this->aiMessage = 'Pick a team first.';
            return;
        }
        if (trim($this->aiInstruction) === '') {
            $this->aiMessage = 'List player names, or describe the squad (e.g. "12 players: 2 GK, 4 DEF, 3 MID, 3 FWD").';
            return;
        }

        $result = $ai->generateRoster($this->aiInstruction);

        if (!$result['success']) {
            $this->aiMessage = $result['message'] . ' Or add players manually.';
            $this->aiPlayers = [];
            return;
        }

        $this->aiPlayers = $result['players'];
        $this->aiWarnings = $result['warnings'];
        $count = count($this->aiPlayers);
        $this->aiMessage = "Built {$count} player(s) — review below, then Create All.";
    }

    public function createAiPlayers(): void
    {
        if (empty($this->aiPlayers) || !$this->aiTeamId)
            return;

        foreach ($this->aiPlayers as $p) {
            Player::create([
                'team_id' => $this->aiTeamId,
                'name' => $p['name'],
                'position' => $p['position'],
                'number' => $p['number'],
                'fantasy_price' => $p['fantasy_price'],
            ]);
        }

        $count = count($this->aiPlayers);
        $this->aiPlayers = [];
        $this->aiInstruction = '';
        $this->aiMessage = '';
        unset($this->players);   // refresh the computed list
        $this->dispatch('notify', message: "{$count} players created.");
    }

    public function save(): void
    {
        $this->validate([
            'team_id' => 'required|exists:teams,id',
            'name' => 'required|string|max:100',
            'position' => 'required|in:GK,DEF,MID,FWD',
            'number' => 'nullable|integer|min:1|max:99',
            'fantasy_price' => 'required|integer|min:1|max:9999',
        ]);

        $data = [
            'team_id' => $this->team_id,
            'name' => $this->name,
            'position' => $this->position,
            'number' => $this->number ?: null,
            'fantasy_price' => (int) $this->fantasy_price,
        ];

        if ($this->editingId) {
            Player::findOrFail($this->editingId)->update($data);
        } else {
            Player::create($data);
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        Player::findOrFail($id)->delete();
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->team_id = '';
        $this->name = '';
        $this->position = '';
        $this->number = '';
        $this->fantasy_price = '50';
    }

    // Position badge styles
    public function positionStyle(string $pos): string
    {
        return match ($pos) {
            'GK' => 'background:rgba(253,212,0,0.08);color:#fdd400;border-color:rgba(253,212,0,0.25);',
            'DEF' => 'background:rgba(59,130,246,0.08);color:#60a5fa;border-color:rgba(59,130,246,0.25);',
            'MID' => 'background:rgba(139,92,246,0.08);color:#a78bfa;border-color:rgba(139,92,246,0.25);',
            'FWD' => 'background:rgba(0,230,118,0.08);color:#00E676;border-color:rgba(0,230,118,0.25);',
            default => 'background:rgba(180,180,180,0.08);color:#9ca3af;border-color:rgba(180,180,180,0.2);',
        };
    }
} ?>

<div class="space-y-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="font-display font-black text-2xl text-on-surface uppercase tracking-tight">Players</h2>
            <p class="text-on-surface-variant/60 text-xs mt-1 font-mono">All players registered in the fantasy draft
                catalog.</p>
        </div>
        <button wire:click="openCreate"
            class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs font-mono font-bold uppercase tracking-wider text-black transition-all duration-200 hover:scale-[1.01] active:scale-[0.99] shadow-lg cursor-pointer flex-shrink-0"
            style="background: linear-gradient(135deg, #00E676 0%, #00b359 100%);">
            <span class="material-symbols-outlined text-[16px]">add</span>
            New Player
        </button>
    </div>
    {{-- AI Roster Builder --}}
    <div class="rounded-2xl border border-[#00E676]/30 p-5" style="background:rgba(0,230,118,0.03);">
        <div class="flex items-center gap-2 mb-2">
            <span class="material-symbols-outlined text-[18px]" style="color:#00E676;">auto_awesome</span>
            <p class="text-[11px] font-mono font-bold uppercase tracking-widest" style="color:#00E676;">AI Roster
                Builder</p>
        </div>
        <p class="font-mono text-[10px] text-on-surface-variant/50 mb-3">
            Pick a team, then paste player names OR describe the squad. AI assigns positions &amp; prices for you to
            review.
        </p>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-3">
            <select wire:model="aiTeamId"
                class="px-4 py-2.5 rounded-xl text-sm text-white border border-outline-variant/20 bg-[#0d110f] font-mono cursor-pointer focus:outline-none focus:border-[#00E676]/50">
                <option value="">Select team…</option>
                @foreach($teams as $team)
                    <option value="{{ $team['id'] }}">{{ $team['name'] }}</option>
                @endforeach
            </select>
            <input wire:model="aiInstruction" type="text"
                placeholder='e.g. "12 players: 2 GK, 4 DEF, 3 MID, 3 FWD" or paste names'
                class="sm:col-span-2 px-4 py-2.5 rounded-xl text-sm text-white border border-outline-variant/20 bg-white/5 font-mono focus:outline-none focus:border-[#00E676]/50" />
        </div>

        <button wire:click="generateRoster" wire:loading.attr="disabled" wire:target="generateRoster"
            class="px-5 py-2.5 rounded-xl text-xs font-mono font-bold uppercase tracking-wider text-black cursor-pointer disabled:opacity-50"
            style="background:linear-gradient(135deg,#00E676 0%,#00b359 100%);">
            <span wire:loading.remove wire:target="generateRoster">✨ Build Roster</span>
            <span wire:loading wire:target="generateRoster">Thinking…</span>
        </button>

        @if($aiMessage)
            <p class="font-mono text-[10px] text-on-surface-variant/70 mt-3">{{ $aiMessage }}</p>
        @endif

        {{-- Proposed players review --}}
        @if(count($aiPlayers) > 0)
            <div class="mt-4 rounded-xl border border-[#00E676]/20 p-3" style="background:rgba(255,255,255,0.02);">
                <p class="text-[10px] font-mono font-bold uppercase tracking-widest mb-2" style="color:#00E676;">
                    Proposed players ({{ count($aiPlayers) }})
                </p>
                <div class="space-y-1.5 max-h-64 overflow-y-auto">
                    @foreach($aiPlayers as $p)
                        <div class="flex items-center gap-3 px-3 py-2 rounded-lg border border-outline-variant/15"
                            style="background:rgba(255,255,255,0.02);">
                            <span class="px-2 py-0.5 rounded font-mono text-[9px] font-bold uppercase border flex-shrink-0"
                                style="{{ $this->positionStyle($p['position']) }}">{{ $p['position'] }}</span>
                            <span class="text-sm text-white font-bold flex-1 truncate">{{ $p['name'] }}</span>
                            @if($p['number'])<span
                            class="font-mono text-[10px] text-on-surface-variant/40">#{{ $p['number'] }}</span>@endif
                            <span class="font-mono text-xs font-bold flex-shrink-0"
                                style="color:#00E676;">{{ $p['fantasy_price'] }}</span>
                        </div>
                    @endforeach
                </div>

                @if(count($aiWarnings) > 0)
                    <div class="mt-2 rounded-lg border border-amber-500/25 p-2.5" style="background:rgba(253,212,0,0.04);">
                        @foreach($aiWarnings as $w)
                            <p class="font-mono text-[10px] text-on-surface-variant/70">• {{ $w }}</p>
                        @endforeach
                    </div>
                @endif

                <button wire:click="createAiPlayers"
                    class="mt-3 px-5 py-2.5 rounded-xl text-xs font-mono font-bold uppercase tracking-wider text-black cursor-pointer"
                    style="background:linear-gradient(135deg,#00E676 0%,#00b359 100%);">
                    ✓ Create All {{ count($aiPlayers) }} Players
                </button>
            </div>
        @endif
    </div>
    {{-- Search + Position filter --}}
    <div class="flex flex-col sm:flex-row gap-3">
        <div class="relative flex-1">
            <span
                class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant/40 text-[16px]">search</span>
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search players..."
                class="w-full pl-9 pr-4 py-2.5 rounded-xl bg-white/5 border border-outline-variant/20 text-sm text-on-surface placeholder:text-on-surface-variant/30 focus:outline-none focus:border-[#00E676]/40 transition-all font-mono" />
        </div>
        <div class="flex gap-2 flex-wrap">
            @foreach(['' => 'All', 'GK' => 'GK', 'DEF' => 'DEF', 'MID' => 'MID', 'FWD' => 'FWD'] as $val => $label)
                    <button wire:click="$set('positionFilter', '{{ $val }}')"
                        class="px-3 py-2 rounded-xl text-xs font-mono font-bold uppercase tracking-wider border transition-all cursor-pointer"
                        style="{{ $positionFilter === $val
                ? 'background:#00E676;color:#000;border-color:#00E676;'
                : 'background:rgba(255,255,255,0.05);color:rgba(255,255,255,0.5);border-color:rgba(255,255,255,0.15);' }}">
                        {{ $label }}
                    </button>
            @endforeach
        </div>
    </div>

    {{-- Table --}}
    <div class="rounded-2xl overflow-hidden border border-outline-variant/15" style="background: rgba(13,17,15,0.8);">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-outline-variant/15" style="background: rgba(255,255,255,0.02);">
                        <th
                            class="py-3.5 px-3 sm:px-5 text-xs font-semibold text-on-surface-variant uppercase tracking-wider font-mono">
                            Name</th>
                        <th
                            class="py-3.5 px-3 sm:px-5 text-xs font-semibold text-on-surface-variant uppercase tracking-wider font-mono">
                            Pos</th>
                        <th
                            class="hidden sm:table-cell py-3.5 px-3 sm:px-5 text-xs font-semibold text-on-surface-variant uppercase tracking-wider font-mono">
                            #</th>
                        <th
                            class="py-3.5 px-3 sm:px-5 text-xs font-semibold text-on-surface-variant uppercase tracking-wider font-mono">
                            Price</th>
                        <th
                            class="hidden sm:table-cell py-3.5 px-3 sm:px-5 text-xs font-semibold text-on-surface-variant uppercase tracking-wider font-mono">
                            Team</th>
                        <th
                            class="hidden md:table-cell py-3.5 px-5 text-xs font-semibold text-on-surface-variant uppercase tracking-wider font-mono">
                            Stats</th>
                        <th
                            class="py-3.5 px-3 sm:px-5 text-xs font-semibold text-on-surface-variant uppercase tracking-wider font-mono text-right">
                            Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->players as $player)
                        @php $pos = is_array($player['position']) ? $player['position']['value'] : $player['position']; @endphp
                        <tr
                            class="border-b border-outline-variant/10 text-sm hover:bg-white/[0.02] transition-all duration-150">

                            <td class="py-4 px-3 sm:px-5 font-bold text-on-surface max-w-[130px] sm:max-w-none"><span
                                    class="block truncate">{{ $player['name'] }}</span></td>

                            {{-- Position badge --}}
                            <td class="py-4 px-3 sm:px-5">
                                <span
                                    class="px-2.5 py-0.5 rounded font-mono text-[9px] font-bold uppercase tracking-widest border"
                                    style="{{ $this->positionStyle($pos) }}">
                                    {{ $pos }}
                                </span>
                            </td>

                            {{-- Number --}}
                            <td class="hidden sm:table-cell py-4 px-3 sm:px-5 text-on-surface-variant/80 font-mono text-xs">
                                {{ $player['number'] ? '#' . $player['number'] : '—' }}
                            </td>

                            {{-- Fantasy price --}}
                            <td class="py-4 px-3 sm:px-5 font-mono text-xs font-bold" style="color:#00E676;">
                                {{ $player['fantasy_price'] }} pts
                            </td>

                            {{-- Team --}}
                            <td
                                class="hidden sm:table-cell py-4 px-3 sm:px-5 text-on-surface-variant/80 font-mono text-xs max-w-[120px]">
                                <span class="block truncate">{{ $player['team']['name'] ?? '—' }}</span>
                            </td>

                            {{-- Quick stats --}}
                            <td class="hidden md:table-cell py-4 px-5">
                                <div class="flex items-center gap-2 font-mono text-[10px]">
                                    <span class="text-[#00E676]" title="Goals">⚽ {{ $player['goals'] }}</span>
                                    <span class="text-purple-400" title="Assists">🅰 {{ $player['assists'] }}</span>
                                    <span class="text-yellow-400" title="Yellows">🟨 {{ $player['yellow_cards'] }}</span>
                                    <span class="text-red-400" title="Reds">🟥 {{ $player['red_cards'] }}</span>
                                </div>
                            </td>

                            {{-- Actions --}}
                            <td class="py-4 px-3 sm:px-5 text-right space-x-1">
                                <button wire:click="openEdit({{ $player['id'] }})"
                                    class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-on-surface-variant/80 border border-outline-variant/20 bg-white/5 hover:text-[#00E676] hover:border-[#00E676]/40 hover:bg-[#00E676]/10 transition-all duration-150 cursor-pointer"
                                    title="Edit">
                                    <span class="material-symbols-outlined text-[16px]">edit</span>
                                </button>
                                <button wire:click="delete({{ $player['id'] }})"
                                    wire:confirm="Delete '{{ $player['name'] }}'? This cannot be undone."
                                    class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-on-surface-variant/80 border border-outline-variant/20 bg-white/5 hover:text-red-400 hover:border-red-400/40 hover:bg-red-400/10 transition-all duration-150 cursor-pointer"
                                    title="Delete">
                                    <span class="material-symbols-outlined text-[16px]">delete</span>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-16 text-center">
                                <span
                                    class="material-symbols-outlined text-4xl text-on-surface-variant/20 block mb-3">sports_soccer</span>
                                <p class="text-on-surface-variant/40 text-sm font-mono">No players yet. Add the first
                                    player.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-outline-variant/15 px-5 py-3.5">
            <span class="font-mono text-[10px] text-on-surface-variant/40">{{ count($this->players) }} player(s)</span>
        </div>
    </div>

    {{-- MODAL --}}
    @if($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
            style="background: rgba(0,0,0,0.7); backdrop-filter: blur(4px);">

            <div class="w-full max-w-md rounded-2xl border border-outline-variant/20 shadow-2xl"
                style="background: #0d110f;">

                {{-- Header --}}
                <div class="flex items-center justify-between px-6 py-4 border-b border-outline-variant/15">
                    <h3 class="font-display font-black text-sm uppercase tracking-wider text-on-surface">
                        {{ $editingId ? 'Edit Player' : 'New Player' }}
                    </h3>
                    <button wire:click="closeModal" class="text-on-surface-variant hover:text-white transition-colors">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                {{-- Body --}}
                <div class="px-6 py-5 space-y-4 max-h-[70vh] overflow-y-auto">

                    {{-- Team --}}
                    <div>
                        <label
                            class="block text-xs font-mono text-on-surface-variant uppercase tracking-wider mb-1.5">Team</label>
                        <select wire:model="team_id"
                            class="w-full px-4 py-2.5 rounded-xl text-sm text-white border border-outline-variant/20 bg-[#0d110f] focus:outline-none focus:border-[#00E676]/50 transition-all appearance-none cursor-pointer">
                            <option value="">— Select team —</option>
                            @foreach($teams as $team)
                                <option value="{{ $team['id'] }}">{{ $team['name'] }}</option>
                            @endforeach
                        </select>
                        @error('team_id') <p class="text-red-400 text-xs font-mono mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Name --}}
                    <div>
                        <label class="block text-xs font-mono text-on-surface-variant uppercase tracking-wider mb-1.5">Full
                            Name</label>
                        <input wire:model="name" type="text" placeholder="e.g. Emeka Okafor"
                            class="w-full px-4 py-2.5 rounded-xl text-sm text-white placeholder-on-surface-variant/30 border border-outline-variant/20 bg-white/5 focus:outline-none focus:border-[#00E676]/50 transition-all" />
                        @error('name') <p class="text-red-400 text-xs font-mono mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Position + Number --}}
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label
                                class="block text-xs font-mono text-on-surface-variant uppercase tracking-wider mb-1.5">Position</label>
                            <select wire:model="position"
                                class="w-full px-4 py-2.5 rounded-xl text-sm text-white border border-outline-variant/20 bg-[#0d110f] focus:outline-none focus:border-[#00E676]/50 transition-all appearance-none cursor-pointer">
                                <option value="">— Select —</option>
                                <option value="GK">GK — Goalkeeper</option>
                                <option value="DEF">DEF — Defender</option>
                                <option value="MID">MID — Midfielder</option>
                                <option value="FWD">FWD — Forward</option>
                            </select>
                            @error('position') <p class="text-red-400 text-xs font-mono mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label
                                class="block text-xs font-mono text-on-surface-variant uppercase tracking-wider mb-1.5">Shirt
                                No. <span class="text-on-surface-variant/40">(optional)</span></label>
                            <input wire:model="number" type="number" min="1" max="99" placeholder="e.g. 9"
                                class="w-full px-4 py-2.5 rounded-xl text-sm text-white placeholder-on-surface-variant/30 border border-outline-variant/20 bg-white/5 focus:outline-none focus:border-[#00E676]/50 transition-all" />
                        </div>
                    </div>

                    {{-- Fantasy price --}}
                    <div>
                        <label class="block text-xs font-mono text-on-surface-variant uppercase tracking-wider mb-1.5">
                            Fantasy Price <span class="text-on-surface-variant/40">(pts)</span>
                        </label>
                        <input wire:model="fantasy_price" type="number" min="1" max="9999" placeholder="50"
                            class="w-full px-4 py-2.5 rounded-xl text-sm text-white placeholder-on-surface-variant/30 border border-outline-variant/20 bg-white/5 focus:outline-none focus:border-[#00E676]/50 transition-all" />
                        @error('fantasy_price') <p class="text-red-400 text-xs font-mono mt-1">{{ $message }}</p> @enderror
                    </div>

                </div>

                {{-- Footer --}}
                <div class="px-6 py-4 border-t border-outline-variant/15 flex items-center justify-end gap-3">
                    <button wire:click="closeModal"
                        class="px-5 py-2 rounded-xl text-xs font-mono font-bold uppercase tracking-wider text-on-surface-variant border border-outline-variant/20 hover:bg-white/5 transition-all cursor-pointer">
                        Cancel
                    </button>
                    <button wire:click="save" wire:loading.attr="disabled"
                        class="px-5 py-2 rounded-xl text-xs font-mono font-bold uppercase tracking-wider text-black transition-all cursor-pointer disabled:opacity-50"
                        style="background: linear-gradient(135deg, #00E676 0%, #00b359 100%);">
                        <span wire:loading.remove wire:target="save">{{ $editingId ? 'Update' : 'Create' }}</span>
                        <span wire:loading wire:target="save">Saving...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

</div>