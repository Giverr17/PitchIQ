<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Tournament;
use App\Enums\TournamentType;
use App\Enums\TournamentStatus;
use Illuminate\Validation\Rule;

new #[Layout('layouts.admin')] class extends Component {

    // Table data
    public $tournaments = [];

    // Form fields
    public string $name = '';
    public string $type = 'faculty_cup';
    public string $season = '';
    public string $status = 'upcoming';
    public int    $active_matchday = 1;
    public int    $squad_size = 11;         
    public string $start_date = '';
    public string $aiDescription = '';
    public array  $aiTeams = [];          // teams the AI proposes, shown for review
    public array  $aiWarnings = [];
    public string $aiMessage = '';

    // State
    public bool $showModal = false;
    public ?int $editingId = null;

    public function mount(): void
    {
        $this->loadTournaments();
        $this->season = date('Y') . '/' . (date('Y') + 1);
    }

    public function loadTournaments(): void
    {
        $this->tournaments = Tournament::latest()->get()->toArray();
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->editingId = null;
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $tournament = Tournament::findOrFail($id);
        $this->editingId = $id;
        $this->name            = $tournament->name;
        $this->type            = $tournament->type->value;
        $this->season          = $tournament->season;
        $this->status          = $tournament->status->value;
        $this->active_matchday = $tournament->active_matchday;
        $this->squad_size      = $tournament->squad_size;     
        $this->start_date      = $tournament->start_date?->format('Y-m-d') ?? '';
        $this->showModal       = true;
    }

    public function parseTournament(\App\Services\AiService $ai): void
{
    if (trim($this->aiDescription) === '') {
        $this->aiMessage = 'Describe the competition first.';
        return;
    }

    $result = $ai->structureTournament($this->aiDescription);

    if (!$result['success']) {
        $this->aiMessage = $result['message'] . ' Or fill the form manually.';
        $this->aiTeams = [];
        return;
    }

    // Fill the tournament form fields for review
    $t = $result['tournament'];
    $this->name            = $t['name'] ?: $this->name;
    $this->type            = $t['type'];
    $this->season          = $t['season'];
    $this->status          = $t['status'];
    $this->squad_size       = $t['squad_size'];
    $this->start_date      = $t['start_date'] ?? '';

    // Hold the proposed teams for review (created on save)
    $this->aiTeams   = $result['teams'];
    $this->aiWarnings = $result['warnings'];

    $teamCount = count($this->aiTeams);
    $this->aiMessage = "Filled the tournament. {$teamCount} team(s) ready to create — review below, then Create.";

    // Open the modal so the admin reviews the filled form
    $this->editingId = null;
    $this->showModal = true;
}

    public function save(): void
    {
        $this->validate([
            'name'            => 'required|string|max:100',
            'type'            => ['required', Rule::enum(TournamentType::class)],
            'season'          => 'required|string|max:20',
            'status'          => ['required', Rule::enum(TournamentStatus::class)],
            'active_matchday' => 'required|integer|min:1',
           'squad_size'      => 'required|integer|in:5,11',
            'start_date'      => 'nullable|date',
        ]);

        $data = [
            'name'            => $this->name,
            'type'            => $this->type,
            'season'          => $this->season,
            'status'          => $this->status,
            'active_matchday' => $this->active_matchday,
            'squad_size'      => $this->squad_size,
            'start_date'      => $this->start_date ?: null,
        ];
if ($this->editingId) {
    Tournament::findOrFail($this->editingId)->update($data);
} else {
    $tournament = Tournament::create($data);

    // If AI proposed teams, create them attached to this new tournament
    foreach ($this->aiTeams as $team) {
        \App\Models\Team::create([
            'tournament_id' => $tournament->id,
            'name'          => $team['name'],
            'faculty'       => $team['faculty'],
            'department'    => $team['department'],
            'colour'        => $team['colour'],
        ]);
    }
    $this->aiTeams = [];   // clear after creating
}

        $this->showModal = false;
        $this->resetForm();
        $this->loadTournaments();
    }

    public function delete(int $id): void
    {
        Tournament::findOrFail($id)->delete();
        $this->loadTournaments();
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->name            = '';
        $this->type            = 'faculty_cup';
        $this->season          = date('Y') . '/' . (date('Y') + 1);
        $this->status          = 'upcoming';
        $this->active_matchday = 1;
        $this->squad_size      = 11; 
        $this->start_date      = '';
        $this->editingId       = null;
    }
} ?>

<div class="space-y-6">

    {{-- Page header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="font-display font-black text-2xl text-on-surface uppercase tracking-tight">Tournaments</h2>
            <p class="text-on-surface-variant/60 text-xs mt-1 font-mono">Campus leagues, cups and knockout competitions.</p>
        </div>
        <button wire:click="openCreate"
                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs font-mono font-bold uppercase tracking-wider text-background bg-primary-container hover:brightness-110 transition-all duration-200 hover:scale-[1.01] active:scale-[0.99] shadow-lg shadow-primary-container/10 cursor-pointer flex-shrink-0"
                style="background: linear-gradient(135deg, #00E676 0%, #00b359 100%);">
            <span class="material-symbols-outlined text-[16px]">add</span>
            New Tournament
        </button>
    </div>

    {{-- AI Quick Setup --}}
<div class="rounded-2xl border border-[#00E676]/20 p-5" style="background:rgba(0,230,118,0.03);">
    <div class="flex items-center gap-2 mb-2">
        <span class="material-symbols-outlined text-[18px]" style="color:#00E676;">auto_awesome</span>
        <p class="text-[11px] font-mono font-bold uppercase tracking-widest" style="color:#00E676;">AI Quick Setup</p>
    </div>
    <p class="font-mono text-[10px] text-on-surface-variant/50 mb-3">
        Describe a competition and its teams — AI sets up the tournament and teams for you to review.
    </p>
    <textarea wire:model="aiDescription" rows="2"
        placeholder="e.g. Create a 5-a-side friendly called Spring Cup for 2025/2026 with teams Alpha FC (Engineering), Bravo United (Sciences), Charlie City, Delta Stars"
        class="w-full rounded-xl text-sm font-mono outline-none p-3 mb-3 text-white"
        style="background:rgba(255,255,255,0.06); border:2px solid rgba(255,255,255,0.15);"></textarea>
    <button wire:click="parseTournament" wire:loading.attr="disabled" wire:target="parseTournament"
        class="px-5 py-2.5 rounded-xl text-xs font-mono font-bold uppercase tracking-wider text-black cursor-pointer disabled:opacity-50"
        style="background:linear-gradient(135deg,#00E676 0%,#00b359 100%);">
        <span wire:loading.remove wire:target="parseTournament">✨ Set up with AI</span>
        <span wire:loading wire:target="parseTournament">Thinking…</span>
    </button>
    @if($aiMessage)
        <p class="font-mono text-[10px] text-on-surface-variant/70 mt-3">{{ $aiMessage }}</p>
    @endif
</div>

    {{-- Table --}}
    <div class="rounded-2xl overflow-hidden border border-outline-variant/15" style="background: rgba(13,17,15,0.8);">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-outline-variant/15" style="background: rgba(255,255,255,0.02);">
                        <th class="py-3.5 px-5 text-xs font-semibold text-on-surface-variant uppercase tracking-wider font-mono">Name</th>
                        <th class="hidden sm:table-cell py-3.5 px-5 text-xs font-semibold text-on-surface-variant uppercase tracking-wider font-mono">Type</th>
                        <th class="hidden sm:table-cell py-3.5 px-5 text-xs font-semibold text-on-surface-variant uppercase tracking-wider font-mono">Season</th>
                        <th class="py-3.5 px-5 text-xs font-semibold text-on-surface-variant uppercase tracking-wider font-mono">Status</th>
                        <th class="hidden md:table-cell py-3.5 px-5 text-xs font-semibold text-on-surface-variant uppercase tracking-wider font-mono">Start Date</th>
                        <th class="py-3.5 px-5 text-xs font-semibold text-on-surface-variant uppercase tracking-wider font-mono text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tournaments as $row)
                        <tr class="border-b border-outline-variant/10 text-sm hover:bg-white/[0.02] transition-all duration-150">
                            <td class="py-4 px-5 font-bold text-on-surface">{{ $row['name'] }}</td>
                            <td class="hidden sm:table-cell py-4 px-5 text-on-surface-variant font-mono text-xs">
                                {{ match($row['type']) {
                                    'faculty_cup'          => 'Faculty Cup',
                                    'departmental_league'  => 'Dept. League',
                                    'friendly'             => 'Friendly',
                                    default                => $row['type'],
                                } }}
                            </td>
                            <td class="hidden sm:table-cell py-4 px-5 text-on-surface-variant/80 font-mono text-xs">{{ $row['season'] }}</td>
                            <td class="py-4 px-5">
                                @php
                                    $badge = match($row['status']) {
                                        'active'    => ['bg' => 'rgba(0,230,118,0.08)',   'color' => '#00E676', 'border' => 'rgba(0,230,118,0.25)',   'label' => 'Active'],
                                        'upcoming'  => ['bg' => 'rgba(253,212,0,0.08)',   'color' => '#fdd400', 'border' => 'rgba(253,212,0,0.25)',   'label' => 'Upcoming'],
                                        'completed' => ['bg' => 'rgba(180,180,180,0.08)', 'color' => '#9ca3af', 'border' => 'rgba(180,180,180,0.2)',  'label' => 'Completed'],
                                        default     => ['bg' => 'rgba(180,180,180,0.08)', 'color' => '#9ca3af', 'border' => 'rgba(180,180,180,0.2)',  'label' => $row['status']],
                                    };
                                @endphp
                                <span class="px-2.5 py-0.5 rounded-full font-mono text-[9px] font-bold uppercase tracking-widest border"
                                      style="background:{{ $badge['bg'] }}; color:{{ $badge['color'] }}; border-color:{{ $badge['border'] }};">
                                    {{ $badge['label'] }}
                                </span>
                            </td>
                            <td class="hidden md:table-cell py-4 px-5 text-on-surface-variant/80 font-mono text-xs">
                                {{ $row['start_date'] ? \Carbon\Carbon::parse($row['start_date'])->format('d M Y') : '—' }}
                            </td>
                            <td class="py-4 px-5 text-right space-x-1">
                                <button wire:click="openEdit({{ $row['id'] }})"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-on-surface-variant/80 border border-outline-variant/20 bg-white/5 hover:text-[#00E676] hover:border-[#00E676]/40 hover:bg-[#00E676]/10 transition-all duration-150 cursor-pointer"
                                        title="Edit">
                                    <span class="material-symbols-outlined text-[16px]">edit</span>
                                </button>
                                <button wire:click="delete({{ $row['id'] }})"
                                        wire:confirm="Delete '{{ $row['name'] }}'? This cannot be undone."
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-on-surface-variant/80 border border-outline-variant/20 bg-white/5 hover:text-red-400 hover:border-red-400/40 hover:bg-red-400/10 transition-all duration-150 cursor-pointer"
                                        title="Delete">
                                    <span class="material-symbols-outlined text-[16px]">delete</span>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-16 text-center">
                                <span class="material-symbols-outlined text-4xl text-on-surface-variant/20 block mb-3">emoji_events</span>
                                <p class="text-on-surface-variant/40 text-sm font-mono">No tournaments yet. Create the first one.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-outline-variant/15 px-5 py-3.5 flex items-center justify-between">
            <span class="font-mono text-[10px] text-on-surface-variant/40">{{ count($tournaments) }} tournament(s)</span>
        </div>
    </div>

    {{-- CREATE / EDIT MODAL --}}
    @if($showModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
         style="background: rgba(0,0,0,0.7); backdrop-filter: blur(4px);">

        <div class="w-full max-w-md rounded-2xl border border-outline-variant/20 shadow-2xl"
             style="background: #0d110f;"
             wire:click.stop>

            {{-- Modal header --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-outline-variant/15">
                <h3 class="font-display font-black text-sm uppercase tracking-wider text-on-surface">
                    {{ $editingId ? 'Edit Tournament' : 'New Tournament' }}
                </h3>
                <button wire:click="closeModal" class="text-on-surface-variant hover:text-white transition-colors">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>
            </div>

            {{-- Modal body --}}
            <div class="px-6 py-5 space-y-4 max-h-[70vh] overflow-y-auto">

                {{-- Name --}}
                <div>
                    <label class="block text-xs font-mono text-on-surface-variant uppercase tracking-wider mb-1.5">Tournament Name</label>
                    <input wire:model="name" type="text" placeholder="e.g. Faculty Cup 2026"
                           class="w-full px-4 py-2.5 rounded-xl text-sm text-white placeholder-on-surface-variant/30 border border-outline-variant/20 bg-white/5 focus:outline-none focus:border-[#00E676]/50 focus:bg-white/[0.07] transition-all"
                           style="font-family: 'Plus Jakarta Sans', sans-serif;" />
                    @error('name') <p class="text-red-400 text-xs font-mono mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Type --}}
                <div>
                    <label class="block text-xs font-mono text-on-surface-variant uppercase tracking-wider mb-1.5">Type</label>
                    <select wire:model="type"
                            class="w-full px-4 py-2.5 rounded-xl text-sm text-white border border-outline-variant/20 bg-[#0d110f] focus:outline-none focus:border-[#00E676]/50 transition-all appearance-none cursor-pointer">
                        @foreach(\App\Enums\TournamentType::cases() as $case)
                            <option value="{{ $case->value }}">{{ $case->label() }}</option>
                        @endforeach
                    </select>
                    @error('type') <p class="text-red-400 text-xs font-mono mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Season + Status --}}
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-mono text-on-surface-variant uppercase tracking-wider mb-1.5">Season</label>
                        <input wire:model="season" type="text" placeholder="2025/2026"
                               class="w-full px-4 py-2.5 rounded-xl text-sm text-white placeholder-on-surface-variant/30 border border-outline-variant/20 bg-white/5 focus:outline-none focus:border-[#00E676]/50 transition-all" />
                        @error('season') <p class="text-red-400 text-xs font-mono mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-mono text-on-surface-variant uppercase tracking-wider mb-1.5">Status</label>
                        <select wire:model="status"
                                class="w-full px-4 py-2.5 rounded-xl text-sm text-white border border-outline-variant/20 bg-[#0d110f] focus:outline-none focus:border-[#00E676]/50 transition-all appearance-none cursor-pointer">
                            @foreach(\App\Enums\TournamentStatus::cases() as $case)
                                <option value="{{ $case->value }}">{{ $case->label() }}</option>
                            @endforeach
                        </select>
                        @error('status') <p class="text-red-400 text-xs font-mono mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

              {{-- Squad Size --}}
<div>
    <label class="block text-xs font-mono text-on-surface-variant uppercase tracking-wider mb-1.5">Squad Size</label>
    <select wire:model="squad_size"
            class="w-full px-4 py-2.5 rounded-xl text-sm text-white border border-outline-variant/20 bg-[#0d110f] focus:outline-none focus:border-[#00E676]/50 transition-all appearance-none cursor-pointer">
        <option value="11">11-a-side (full squad)</option>
        <option value="5">5-a-side (small squad)</option>
    </select>
    @error('squad_size') <p class="text-red-400 text-xs font-mono mt-1">{{ $message }}</p> @enderror
</div>
{{-- AI-proposed teams (review before create) --}}
@if(count($aiTeams) > 0)
    <div class="rounded-xl border border-[#00E676]/20 p-3" style="background:rgba(0,230,118,0.03);">
        <p class="text-[10px] font-mono font-bold uppercase tracking-widest mb-2" style="color:#00E676;">
            Teams to create ({{ count($aiTeams) }})
        </p>
        <div class="space-y-1.5">
            @foreach($aiTeams as $team)
                <div class="flex items-center gap-2.5 px-2.5 py-1.5 rounded-lg border border-outline-variant/15" style="background:rgba(255,255,255,0.02);">
                    <span class="w-3 h-3 rounded-full flex-shrink-0" style="background:{{ $team['colour'] }};"></span>
                    <span class="text-sm text-white font-bold">{{ $team['name'] }}</span>
                    <span class="font-mono text-[10px] text-on-surface-variant/50 ml-auto">{{ $team['faculty'] ?? '—' }}</span>
                </div>
            @endforeach
        </div>
    </div>
    @if(count($aiWarnings) > 0)
        <div class="rounded-lg border border-amber-500/25 p-2.5" style="background:rgba(253,212,0,0.04);">
            @foreach($aiWarnings as $w)
                <p class="font-mono text-[10px] text-on-surface-variant/70">• {{ $w }}</p>
            @endforeach
        </div>
    @endif
@endif
{{-- Active Matchday + Start Date --}}
<div class="grid grid-cols-2 gap-3">
    <div>
        <label class="block text-xs font-mono text-on-surface-variant uppercase tracking-wider mb-1.5">Active Matchday</label>
        <input wire:model="active_matchday" type="number" min="1"
               class="w-full px-4 py-2.5 rounded-xl text-sm text-white border border-outline-variant/20 bg-white/5 focus:outline-none focus:border-[#00E676]/50 transition-all" />
        @error('active_matchday') <p class="text-red-400 text-xs font-mono mt-1">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="block text-xs font-mono text-on-surface-variant uppercase tracking-wider mb-1.5">Start Date <span class="text-on-surface-variant/40">(optional)</span></label>
        <input wire:model="start_date" type="date"
               class="w-full px-4 py-2.5 rounded-xl text-sm text-white border border-outline-variant/20 bg-white/5 focus:outline-none focus:border-[#00E676]/50 transition-all" />
        @error('start_date') <p class="text-red-400 text-xs font-mono mt-1">{{ $message }}</p> @enderror
    </div>
</div>
            </div>

            {{-- Modal footer --}}
            <div class="px-6 py-4 border-t border-outline-variant/15 flex items-center justify-end gap-3">
                <button wire:click="closeModal"
                        class="px-5 py-2 rounded-xl text-xs font-mono font-bold uppercase tracking-wider text-on-surface-variant border border-outline-variant/20 hover:bg-white/5 transition-all cursor-pointer">
                    Cancel
                </button>
                <button wire:click="save"
                        wire:loading.attr="disabled"
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