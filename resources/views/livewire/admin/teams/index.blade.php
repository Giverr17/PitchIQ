<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use App\Models\Team;
use App\Models\Tournament;

new #[Layout('layouts.admin')] class extends Component {

    public $tournaments = [];

    public string $search = '';

    // Form fields
    public int|string $tournament_id = '';
    public string $name = '';
    public string $faculty = '';
    public string $department = '';
    public string $colour = '#00E676';

    // State
    public bool $showModal = false;
    public ?int $editingId = null;

    // AI Team Builder
    public int|string $aiTournamentId = '';
    public string $aiInstruction = '';
    public array $aiTeams = [];
    public array $aiWarnings = [];
    public string $aiMessage = '';

    public function mount(): void
    {
        $this->tournaments = Tournament::orderBy('name')->get(['id', 'name'])->toArray();
    }

    #[Computed]
    public function teams(): array
    {
        return Team::with('tournament')
            ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->latest()
            ->get()
            ->toArray();
    }

    public function generateTeams(\App\Services\AiService $ai): void
    {
        if (!$this->aiTournamentId) {
            $this->aiMessage = 'Pick a tournament first.';
            return;
        }
        if (trim($this->aiInstruction) === '') {
            $this->aiMessage = 'Describe the teams (e.g. "8 faculty teams") or paste faculty/department names.';
            return;
        }

        $result = $ai->generateTeams($this->aiInstruction);

        if (!$result['success']) {
            $this->aiMessage = $result['message'] . ' Or add teams manually.';
            $this->aiTeams = [];
            return;
        }

        $this->aiTeams = $result['teams'];
        $this->aiWarnings = $result['warnings'];
        $count = count($this->aiTeams);
        $this->aiMessage = "Built {$count} team(s) — review below, then Create All.";
    }

    public function createAiTeams(): void
    {
        if (empty($this->aiTeams) || !$this->aiTournamentId) {
            return;
        }

        foreach ($this->aiTeams as $t) {
            Team::create([
                'tournament_id' => $this->aiTournamentId,
                'name' => $t['name'],
                'faculty' => $t['faculty'] ?? null,
                'department' => $t['department'] ?? null,
                'colour' => $t['colour'] ?? '#00E676',
            ]);
        }

        $count = count($this->aiTeams);
        $this->aiTeams = [];
        $this->aiInstruction = '';
        $this->aiMessage = '';
        unset($this->teams);   // refresh the computed list
        $this->dispatch('notify', message: "{$count} teams created.");
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $team = Team::findOrFail($id);
        $this->editingId = $id;
        $this->tournament_id = $team->tournament_id;
        $this->name = $team->name;
        $this->faculty = $team->faculty ?? '';
        $this->department = $team->department ?? '';
        $this->colour = $team->colour;
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate([
            'tournament_id' => 'required|exists:tournaments,id',
            'name' => 'required|string|max:100',
            'faculty' => 'nullable|string|max:100',
            'department' => 'nullable|string|max:100',
            'colour' => 'required|string|max:7',
        ]);

        $data = [
            'tournament_id' => $this->tournament_id,
            'name' => $this->name,
            'faculty' => $this->faculty ?: null,
            'department' => $this->department ?: null,
            'colour' => $this->colour,
        ];

        if ($this->editingId) {
            Team::findOrFail($this->editingId)->update($data);
        } else {
            Team::create($data);
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        Team::findOrFail($id)->delete();
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->tournament_id = '';
        $this->name = '';
        $this->faculty = '';
        $this->department = '';
        $this->colour = '#00E676';
    }
} ?>

<div class="space-y-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="font-display font-black text-2xl text-on-surface uppercase tracking-tight">Teams</h2>
            <p class="text-on-surface-variant/60 text-xs mt-1 font-mono">Departmental and faculty teams registered on
                the platform.</p>
        </div>
        <button wire:click="openCreate"
            class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs font-mono font-bold uppercase tracking-wider text-black transition-all duration-200 hover:scale-[1.01] active:scale-[0.99] shadow-lg cursor-pointer flex-shrink-0"
            style="background: linear-gradient(135deg, #00E676 0%, #00b359 100%);">
            <span class="material-symbols-outlined text-[16px]">add</span>
            New Team
        </button>
    </div>

    {{-- AI Team Builder --}}
    <div class="rounded-2xl border border-[#00E676]/30 p-5" style="background:rgba(0,230,118,0.03);">
        <div class="flex items-center gap-2 mb-2">
            <span class="material-symbols-outlined text-[18px]" style="color:#00E676;">auto_awesome</span>
            <p class="text-[11px] font-mono font-bold uppercase tracking-widest" style="color:#00E676;">AI Team Builder</p>
        </div>
        <p class="font-mono text-[10px] text-on-surface-variant/50 mb-3">
            Pick a tournament, then describe the teams or paste faculty/department names. AI proposes teams &amp; colours
            for you to review.
        </p>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-3">
            <select wire:model="aiTournamentId"
                class="px-4 py-2.5 rounded-xl text-sm text-white border border-outline-variant/20 bg-[#0d110f] font-mono cursor-pointer focus:outline-none focus:border-[#00E676]/50">
                <option value="">Select tournament…</option>
                @foreach($tournaments as $t)
                    <option value="{{ $t['id'] }}">{{ $t['name'] }}</option>
                @endforeach
            </select>
            <input wire:model="aiInstruction" type="text" placeholder='e.g. "8 faculty teams" or paste faculty names'
                class="sm:col-span-2 px-4 py-2.5 rounded-xl text-sm text-white border border-outline-variant/20 bg-white/5 font-mono focus:outline-none focus:border-[#00E676]/50" />
        </div>

        <button wire:click="generateTeams" wire:loading.attr="disabled" wire:target="generateTeams"
            class="px-5 py-2.5 rounded-xl text-xs font-mono font-bold uppercase tracking-wider text-black cursor-pointer disabled:opacity-50"
            style="background:linear-gradient(135deg,#00E676 0%,#00b359 100%);">
            <span wire:loading.remove wire:target="generateTeams">✨ Build Teams</span>
            <span wire:loading wire:target="generateTeams">Thinking…</span>
        </button>

        @if($aiMessage)
            <p class="font-mono text-[10px] text-on-surface-variant/70 mt-3">{{ $aiMessage }}</p>
        @endif

        {{-- Proposed teams review --}}
        @if(count($aiTeams) > 0)
            <div class="mt-4 rounded-xl border border-[#00E676]/20 p-3" style="background:rgba(255,255,255,0.02);">
                <p class="text-[10px] font-mono font-bold uppercase tracking-widest mb-2" style="color:#00E676;">
                    Proposed teams ({{ count($aiTeams) }})
                </p>
                <div class="space-y-1.5 max-h-64 overflow-y-auto">
                    @foreach($aiTeams as $t)
                        <div class="flex items-center gap-3 px-3 py-2 rounded-lg border border-outline-variant/15"
                            style="background:rgba(255,255,255,0.02);">
                            <span class="w-3 h-3 rounded-full flex-shrink-0"
                                style="background:{{ $t['colour'] }}; box-shadow:0 0 8px {{ $t['colour'] }}55;"></span>
                            <span class="text-sm text-white font-bold flex-1 truncate">{{ $t['name'] }}</span>
                            @if($t['faculty'])
                                <span class="font-mono text-[10px] text-on-surface-variant/50">{{ $t['faculty'] }}</span>
                            @endif
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

                <button wire:click="createAiTeams"
                    class="mt-3 px-5 py-2.5 rounded-xl text-xs font-mono font-bold uppercase tracking-wider text-black cursor-pointer"
                    style="background:linear-gradient(135deg,#00E676 0%,#00b359 100%);">
                    ✓ Create All {{ count($aiTeams) }} Teams
                </button>
            </div>
        @endif
    </div>

    {{-- Search --}}
    <div class="relative">
        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant/40 text-[16px]">search</span>
        <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search teams..."
               class="w-full pl-9 pr-4 py-2.5 rounded-xl bg-white/5 border border-outline-variant/20 text-sm text-on-surface placeholder:text-on-surface-variant/30 focus:outline-none focus:border-[#00E676]/40 transition-all font-mono" />
    </div>

    {{-- Table --}}
    <div class="rounded-2xl overflow-hidden border border-outline-variant/15" style="background: rgba(13,17,15,0.8);">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-outline-variant/15" style="background: rgba(255,255,255,0.02);">
                        <th
                            class="py-3.5 px-5 text-xs font-semibold text-on-surface-variant uppercase tracking-wider font-mono">
                            Name</th>
                        <th
                            class="py-3.5 px-5 text-xs font-semibold text-on-surface-variant uppercase tracking-wider font-mono">
                            Faculty</th>
                        <th
                            class="hidden sm:table-cell py-3.5 px-5 text-xs font-semibold text-on-surface-variant uppercase tracking-wider font-mono">
                            Department</th>
                        <th
                            class="hidden sm:table-cell py-3.5 px-5 text-xs font-semibold text-on-surface-variant uppercase tracking-wider font-mono">
                            Colour</th>
                        <th
                            class="hidden md:table-cell py-3.5 px-5 text-xs font-semibold text-on-surface-variant uppercase tracking-wider font-mono">
                            Tournament</th>
                        <th
                            class="py-3.5 px-5 text-xs font-semibold text-on-surface-variant uppercase tracking-wider font-mono text-right">
                            Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->teams as $team)
                        <tr
                            class="border-b border-outline-variant/10 text-sm hover:bg-white/[0.02] transition-all duration-150">

                            {{-- Name + colour dot --}}
                            <td class="py-4 px-5">
                                <div class="flex items-center gap-2.5">
                                    <span class="w-3 h-3 rounded-full flex-shrink-0"
                                        style="background:{{ $team['colour'] }}; box-shadow: 0 0 8px {{ $team['colour'] }}55;"></span>
                                    <span class="font-bold text-on-surface">{{ $team['name'] }}</span>
                                </div>
                            </td>

                            <td class="py-4 px-5 text-on-surface-variant font-mono text-xs">{{ $team['faculty'] ?? '—' }}
                            </td>
                            <td class="hidden sm:table-cell py-4 px-5 text-on-surface-variant/80 text-xs">{{ $team['department'] ?? '—' }}</td>

                            {{-- Colour swatch --}}
                            <td class="hidden sm:table-cell py-4 px-5">
                                <div class="flex items-center gap-2">
                                    <span class="w-5 h-5 rounded-md border border-white/10 flex-shrink-0"
                                        style="background:{{ $team['colour'] }};"></span>
                                    <span
                                        class="font-mono text-[9px] font-bold text-on-surface-variant/60">{{ $team['colour'] }}</span>
                                </div>
                            </td>

                            {{-- Tournament name --}}
                            <td class="hidden md:table-cell py-4 px-5 text-on-surface-variant/80 font-mono text-xs">
                                {{ $team['tournament']['name'] ?? '—' }}
                            </td>

                            {{-- Actions --}}
                            <td class="py-4 px-5 text-right space-x-1">
                                <button wire:click="openEdit({{ $team['id'] }})"
                                    class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-on-surface-variant/80 border border-outline-variant/20 bg-white/5 hover:text-[#00E676] hover:border-[#00E676]/40 hover:bg-[#00E676]/10 transition-all duration-150 cursor-pointer"
                                    title="Edit">
                                    <span class="material-symbols-outlined text-[16px]">edit</span>
                                </button>
                                <button wire:click="delete({{ $team['id'] }})"
                                    wire:confirm="Delete '{{ $team['name'] }}'? All players in this team will also be deleted."
                                    class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-on-surface-variant/80 border border-outline-variant/20 bg-white/5 hover:text-red-400 hover:border-red-400/40 hover:bg-red-400/10 transition-all duration-150 cursor-pointer"
                                    title="Delete">
                                    <span class="material-symbols-outlined text-[16px]">delete</span>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-16 text-center">
                                <span
                                    class="material-symbols-outlined text-4xl text-on-surface-variant/20 block mb-3">groups</span>
                                <p class="text-on-surface-variant/40 text-sm font-mono">No teams yet. Add the first team.
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-outline-variant/15 px-5 py-3.5">
            <span class="font-mono text-[10px] text-on-surface-variant/40">{{ count($this->teams) }} team(s)</span>
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
                        {{ $editingId ? 'Edit Team' : 'New Team' }}
                    </h3>
                    <button wire:click="closeModal" class="text-on-surface-variant hover:text-white transition-colors">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                {{-- Body --}}
                <div class="px-6 py-5 space-y-4 max-h-[70vh] overflow-y-auto">

                    {{-- Tournament select --}}
                    <div>
                        <label
                            class="block text-xs font-mono text-on-surface-variant uppercase tracking-wider mb-1.5">Tournament</label>
                        <select wire:model="tournament_id"
                            class="w-full px-4 py-2.5 rounded-xl text-sm text-white border border-outline-variant/20 bg-[#0d110f] focus:outline-none focus:border-[#00E676]/50 transition-all appearance-none cursor-pointer">
                            <option value="">— Select tournament —</option>
                            @foreach($tournaments as $t)
                                <option value="{{ $t['id'] }}">{{ $t['name'] }}</option>
                            @endforeach
                        </select>
                        @error('tournament_id') <p class="text-red-400 text-xs font-mono mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Team name --}}
                    <div>
                        <label class="block text-xs font-mono text-on-surface-variant uppercase tracking-wider mb-1.5">Team
                            Name</label>
                        <input wire:model="name" type="text" placeholder="e.g. CSC FC"
                            class="w-full px-4 py-2.5 rounded-xl text-sm text-white placeholder-on-surface-variant/30 border border-outline-variant/20 bg-white/5 focus:outline-none focus:border-[#00E676]/50 transition-all" />
                        @error('name') <p class="text-red-400 text-xs font-mono mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Faculty + Department --}}
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label
                                class="block text-xs font-mono text-on-surface-variant uppercase tracking-wider mb-1.5">Faculty
                                <span class="text-on-surface-variant/40">(optional)</span></label>
                            <input wire:model="faculty" type="text" placeholder="e.g. Engineering"
                                class="w-full px-4 py-2.5 rounded-xl text-sm text-white placeholder-on-surface-variant/30 border border-outline-variant/20 bg-white/5 focus:outline-none focus:border-[#00E676]/50 transition-all" />
                        </div>
                        <div>
                            <label
                                class="block text-xs font-mono text-on-surface-variant uppercase tracking-wider mb-1.5">Department
                                <span class="text-on-surface-variant/40">(optional)</span></label>
                            <input wire:model="department" type="text" placeholder="e.g. Computer Sci."
                                class="w-full px-4 py-2.5 rounded-xl text-sm text-white placeholder-on-surface-variant/30 border border-outline-variant/20 bg-white/5 focus:outline-none focus:border-[#00E676]/50 transition-all" />
                        </div>
                    </div>

                    {{-- Colour picker --}}
                    <div>
                        <label class="block text-xs font-mono text-on-surface-variant uppercase tracking-wider mb-1.5">Team
                            Colour</label>
                        <div class="flex items-center gap-3" x-data>
                            <input type="color" :value="$wire.colour" x-on:input="$wire.colour = $event.target.value"
                                class="w-10 h-10 rounded-lg border border-outline-variant/20 bg-white/5 cursor-pointer p-0.5" />
                            <span class="font-mono text-xs text-on-surface-variant" x-text="$wire.colour"></span>
                            <span class="w-6 h-6 rounded-full border border-white/10"
                                :style="`background: ${$wire.colour}; box-shadow: 0 0 10px ${$wire.colour}55`"></span>
                        </div>
                        @error('colour') <p class="text-red-400 text-xs font-mono mt-1">{{ $message }}</p> @enderror
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