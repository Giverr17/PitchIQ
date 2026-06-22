<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use App\Models\Fixture;
use App\Models\Team;
use App\Models\Tournament;
use App\Enums\FixtureStatus;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

new #[Layout('layouts.admin')] class extends Component {

    // ── Table state ────────────────────────────────────────────
    public string $search = '';
    public string $filterStatus = '';
    public int $filterMatchday = 0;

    // ── Modal state ────────────────────────────────────────────
    public bool $showModal = false;
    public bool $isEditing = false;
    public ?int $editingId = null;

    // ── Form fields ────────────────────────────────────────────
    public int $tournament_id = 0;
    public int $home_team_id = 0;
    public int $away_team_id = 0;
    public int $matchday = 1;
    public string $date = '';
    public string $status = 'scheduled';
    public ?int $home_score = null;
    public ?int $away_score = null;
    public int $aiTournamentId = 0;     // which tournament to generate for
    public string $aiInstruction = '';
    public array $aiFixtures = [];   // proposed fixtures for review
    public array $aiWarnings = [];
    public string $aiMessage = '';

    // ── Delete confirm ─────────────────────────────────────────
    public bool $showDeleteModal = false;
    public ?int $deletingId = null;

    public function createAiFixtures(): void
    {
        if (empty($this->aiFixtures))
            return;

        foreach ($this->aiFixtures as $f) {
            Fixture::create([
                'tournament_id' => $this->aiTournamentId,
                'home_team_id' => $f['home_team_id'],
                'away_team_id' => $f['away_team_id'],
                'matchday' => $f['matchday'],
                'date' => null,           // admin can set dates later per-fixture
                'status' => 'scheduled',
            ]);
        }

        $count = count($this->aiFixtures);
        $this->aiFixtures = [];
        $this->aiInstruction = '';
        $this->aiMessage = '';
        $this->dispatch('notify', message: "{$count} fixtures created.");
        unset($this->fixtures);   // refresh the computed list
    }

    public function generateFixtures(\App\Services\AiService $ai): void
    {
        if (!$this->aiTournamentId) {
            $this->aiMessage = 'Pick a tournament first.';
            return;
        }
        if (trim($this->aiInstruction) === '') {
            $this->aiMessage = 'Describe the schedule (e.g. "round robin, each team plays once").';
            return;
        }

        // Load ONLY this tournament's teams (fixture-scoping safety)
        $teams = Team::where('tournament_id', $this->aiTournamentId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn($t) => ['id' => $t->id, 'name' => $t->name])
            ->toArray();

        if (count($teams) < 2) {
            $this->aiMessage = 'This tournament needs at least 2 teams to generate fixtures.';
            return;
        }

        $result = $ai->generateFixtures($teams, $this->aiInstruction);

        if (!$result['success']) {
            $this->aiMessage = $result['message'] . ' Or add fixtures manually.';
            $this->aiFixtures = [];
            return;
        }

        // Attach team names for the review display
        $teamNames = collect($teams)->pluck('name', 'id');
        $this->aiFixtures = collect($result['fixtures'])->map(fn($f) => [
            'home_team_id' => $f['home_team_id'],
            'away_team_id' => $f['away_team_id'],
            'matchday' => $f['matchday'],
            'home_team_name' => $teamNames[$f['home_team_id']] ?? '?',
            'away_team_name' => $teamNames[$f['away_team_id']] ?? '?',
        ])->toArray();

        $this->aiWarnings = $result['warnings'];
        $count = count($this->aiFixtures);
        $this->aiMessage = "Generated {$count} fixture(s) — review below, then Create All.";
    }
    // ── Computed ───────────────────────────────────────────────
    #[Computed]
    public function fixtures()
    {
        return Fixture::with(['homeTeam', 'awayTeam', 'tournament'])
            ->when(
                $this->search,
                fn($q) =>
                $q->whereHas('homeTeam', fn($q) => $q->where('name', 'like', "%{$this->search}%"))
                    ->orWhereHas('awayTeam', fn($q) => $q->where('name', 'like', "%{$this->search}%"))
            )
            ->when($this->filterStatus, fn($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterMatchday, fn($q) => $q->where('matchday', $this->filterMatchday))
            ->orderBy('matchday')
            ->orderBy('date')
            ->get();
    }

    #[Computed]
    public function teams()
    {
        return Team::orderBy('name')->get();
    }

    #[Computed]
    public function tournaments()
    {
        return Tournament::orderBy('name')->get();
    }

    #[Computed]
    public function statuses()
    {
        return FixtureStatus::cases();
    }

    #[Computed]
    public function matchdays()
    {
        return Fixture::distinct()->orderBy('matchday')->pluck('matchday');
    }

    // ── Modal open/close ───────────────────────────────────────
    public function openCreate(): void
    {
        $this->resetForm();
        $this->isEditing = false;
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $fixture = Fixture::findOrFail($id);

        $this->editingId = $id;
        $this->tournament_id = $fixture->tournament_id;
        $this->home_team_id = $fixture->home_team_id;
        $this->away_team_id = $fixture->away_team_id;
        $this->matchday = $fixture->matchday;
        $this->date = $fixture->date
            ? Carbon::parse($fixture->date)->format('Y-m-d\TH:i')
            : '';
        $this->status = $fixture->status instanceof FixtureStatus
            ? $fixture->status->value
            : $fixture->status;
        $this->home_score = $fixture->home_score;
        $this->away_score = $fixture->away_score;

        $this->isEditing = true;
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    // ── Save ───────────────────────────────────────────────────
    public function save(): void
    {
        $this->validate([
            'tournament_id' => 'required|exists:tournaments,id',
            'home_team_id' => 'required|exists:teams,id|different:away_team_id',
            'away_team_id' => 'required|exists:teams,id',
            'matchday' => 'required|integer|min:1|max:50',
            'date' => 'nullable|date',
            'status' => ['required', Rule::enum(FixtureStatus::class)],
            'home_score' => 'nullable|integer|min:0',
            'away_score' => 'nullable|integer|min:0',
        ]);

        $data = [
            'tournament_id' => $this->tournament_id,
            'home_team_id' => $this->home_team_id,
            'away_team_id' => $this->away_team_id,
            'matchday' => $this->matchday,
            'date' => $this->date ?: null,
            'status' => $this->status,
            'home_score' => in_array($this->status, ['live', 'completed']) ? $this->home_score : null,
            'away_score' => in_array($this->status, ['live', 'completed']) ? $this->away_score : null,
        ];

        if ($this->isEditing) {
            Fixture::findOrFail($this->editingId)->update($data);
            $this->dispatch('notify', message: 'Fixture updated.');
        } else {
            Fixture::create($data);
            $this->dispatch('notify', message: 'Fixture created.');
        }

        $this->closeModal();
    }

    // ── Delete ─────────────────────────────────────────────────
    public function confirmDelete(int $id): void
    {
        $this->deletingId = $id;
        $this->showDeleteModal = true;
    }

    public function delete(): void
    {
        if ($this->deletingId) {
            Fixture::findOrFail($this->deletingId)->delete();
            $this->dispatch('notify', message: 'Fixture deleted.');
        }
        $this->showDeleteModal = false;
        $this->deletingId = null;
    }

    public function cancelDelete(): void
    {
        $this->showDeleteModal = false;
        $this->deletingId = null;
    }

    // ── Helpers ────────────────────────────────────────────────
    private function resetForm(): void
    {
        $this->editingId = null;
        $this->tournament_id = 0;
        $this->home_team_id = 0;
        $this->away_team_id = 0;
        $this->matchday = 1;
        $this->date = '';
        $this->status = 'scheduled';
        $this->home_score = null;
        $this->away_score = null;
        $this->resetValidation();
    }

} ?>

<div class="space-y-6" x-data>

    {{-- ── Header ──────────────────────────────────────────────── --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="font-display font-black text-2xl text-on-surface uppercase tracking-tight">Fixtures</h2>
            <p class="text-on-surface-variant/60 text-xs mt-1 font-mono">Schedule and manage departmental match
                fixtures.</p>
        </div>
        <button wire:click="openCreate"
            class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs font-mono font-bold uppercase tracking-wider text-background bg-primary-container hover:bg-primary-fixed transition-all duration-200 hover:scale-[1.01] active:scale-[0.99] shadow-lg shadow-primary-container/10 flex-shrink-0 cursor-pointer">
            <span class="material-symbols-outlined text-[16px]">add</span>
            Add Fixture
        </button>
    </div>

    {{-- AI Fixture Generator --}}
    <div class="rounded-2xl border border-primary-container/30 p-5" style="background:rgba(0,230,118,0.03);">
        <div class="flex items-center gap-2 mb-2">
            <span class="material-symbols-outlined text-[18px]" style="color:#00E676;">auto_awesome</span>
            <p class="text-[11px] font-mono font-bold uppercase tracking-widest" style="color:#00E676;">AI Fixture
                Generator</p>
        </div>
        <p class="font-mono text-[10px] text-on-surface-variant/50 mb-3">
            Pick a tournament and describe the schedule — AI builds the fixtures for you to review.
        </p>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-3">
            <select wire:model="aiTournamentId"
                class="px-3.5 py-2.5 rounded-xl bg-surface-container/40 border border-outline-variant/20 text-sm text-on-surface font-mono cursor-pointer focus:outline-none focus:border-primary-container/50">
                <option value="0">Select tournament…</option>
                @foreach($this->tournaments as $t)
                    <option value="{{ $t->id }}">{{ $t->name }}</option>
                @endforeach
            </select>
            <input wire:model="aiInstruction" type="text" placeholder="e.g. round robin, each team plays once"
                class="sm:col-span-2 px-3.5 py-2.5 rounded-xl bg-surface-container/40 border border-outline-variant/20 text-sm text-on-surface font-mono focus:outline-none focus:border-primary-container/50" />
        </div>

        <button wire:click="generateFixtures" wire:loading.attr="disabled" wire:target="generateFixtures"
            class="px-5 py-2.5 rounded-xl text-xs font-mono font-bold uppercase tracking-wider text-black cursor-pointer disabled:opacity-50"
            style="background:linear-gradient(135deg,#00E676 0%,#00b359 100%);">
            <span wire:loading.remove wire:target="generateFixtures">✨ Generate Fixtures</span>
            <span wire:loading wire:target="generateFixtures">Thinking…</span>
        </button>

        @if($aiMessage)
            <p class="font-mono text-[10px] text-on-surface-variant/70 mt-3">{{ $aiMessage }}</p>
        @endif

        {{-- Proposed fixtures review --}}
        @if(count($aiFixtures) > 0)
            <div class="mt-4 rounded-xl border border-primary-container/20 p-3" style="background:rgba(255,255,255,0.02);">
                <p class="text-[10px] font-mono font-bold uppercase tracking-widest mb-2" style="color:#00E676;">
                    Proposed fixtures ({{ count($aiFixtures) }})
                </p>
                <div class="space-y-1.5 max-h-64 overflow-y-auto">
                    @foreach($aiFixtures as $f)
                        <div class="flex items-center gap-3 px-3 py-2 rounded-lg border border-outline-variant/15"
                            style="background:rgba(255,255,255,0.02);">
                            <span
                                class="font-mono text-[9px] font-bold text-on-surface-variant bg-white/5 px-2 py-0.5 rounded border border-outline-variant/20 flex-shrink-0">MD{{ $f['matchday'] }}</span>
                            <span class="text-sm text-on-surface font-bold">{{ $f['home_team_name'] }}</span>
                            <span class="text-on-surface-variant/40 font-mono text-xs">vs</span>
                            <span class="text-sm text-on-surface font-bold">{{ $f['away_team_name'] }}</span>
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

                <button wire:click="createAiFixtures"
                    class="mt-3 px-5 py-2.5 rounded-xl text-xs font-mono font-bold uppercase tracking-wider text-black cursor-pointer"
                    style="background:linear-gradient(135deg,#00E676 0%,#00b359 100%);">
                    ✓ Create All {{ count($aiFixtures) }} Fixtures
                </button>
            </div>
        @endif
    </div>

    {{-- ── Filters ──────────────────────────────────────────────── --}}
    <div class="flex flex-col sm:flex-row gap-3">
        <div class="relative flex-1">
            <span
                class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant/40 text-[16px]">search</span>
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search teams..."
                class="w-full pl-9 pr-4 py-2.5 rounded-xl bg-surface-container/40 border border-outline-variant/20 text-sm text-on-surface placeholder:text-on-surface-variant/30 focus:outline-none focus:border-primary-container/40 focus:bg-surface-container/60 transition-all font-mono" />
        </div>
        <select wire:model.live="filterStatus"
            class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-surface-container/40 border border-outline-variant/20 text-xs font-mono text-on-surface-variant focus:outline-none focus:border-primary-container/40 transition-all cursor-pointer">
            <option value="">All Statuses</option>
            @foreach($this->statuses as $s)
                <option value="{{ $s->value }}">{{ $s->label() }}</option>
            @endforeach
        </select>
        <select wire:model.live="filterMatchday"
            class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-surface-container/40 border border-outline-variant/20 text-xs font-mono text-on-surface-variant focus:outline-none focus:border-primary-container/40 transition-all cursor-pointer">
            <option value="0">All Matchdays</option>
            @foreach($this->matchdays as $md)
                <option value="{{ $md }}">Matchday {{ $md }}</option>
            @endforeach
        </select>
    </div>

    {{-- ── Table ────────────────────────────────────────────────── --}}
    <div class="neo-surface rounded-2xl overflow-hidden border border-outline-variant/15">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-outline-variant/15 bg-surface-container/30">
                        <th
                            class="py-3.5 px-3 sm:px-5 text-xs font-semibold text-on-surface-variant uppercase tracking-wider font-mono">
                            Home</th>
                        <th
                            class="py-3.5 px-3 sm:px-5 text-xs font-semibold text-on-surface-variant uppercase tracking-wider font-mono">
                            Away</th>
                        <th
                            class="hidden sm:table-cell py-3.5 px-3 sm:px-5 text-xs font-semibold text-on-surface-variant uppercase tracking-wider font-mono">
                            MD</th>
                        <th
                            class="hidden md:table-cell py-3.5 px-5 text-xs font-semibold text-on-surface-variant uppercase tracking-wider font-mono">
                            Date</th>
                        <th
                            class="hidden sm:table-cell py-3.5 px-3 sm:px-5 text-xs font-semibold text-on-surface-variant uppercase tracking-wider font-mono">
                            Score</th>
                        <th
                            class="py-3.5 px-3 sm:px-5 text-xs font-semibold text-on-surface-variant uppercase tracking-wider font-mono">
                            Status</th>
                        <th
                            class="py-3.5 px-3 sm:px-5 text-xs font-semibold text-on-surface-variant uppercase tracking-wider font-mono text-right">
                            Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->fixtures as $fixture)
                        @php
                            $status = $fixture->status instanceof FixtureStatus
                                ? $fixture->status
                                : FixtureStatus::from($fixture->status);
                        @endphp
                        <tr
                            class="border-b border-outline-variant/10 text-sm hover:bg-primary-container/[0.025] transition-all duration-150">
                            <td class="py-4 px-3 sm:px-5 font-bold text-on-surface max-w-[120px] sm:max-w-none"><span
                                    class="block truncate">{{ $fixture->homeTeam->name }}</span></td>
                            <td class="py-4 px-3 sm:px-5 font-bold text-on-surface max-w-[120px] sm:max-w-none"><span
                                    class="block truncate">{{ $fixture->awayTeam->name }}</span></td>
                            <td class="hidden sm:table-cell py-4 px-3 sm:px-5">
                                <span
                                    class="font-mono text-[9px] font-bold text-on-surface-variant bg-surface-container/30 px-2 py-0.5 rounded border border-outline-variant/20">
                                    MD{{ $fixture->matchday }}
                                </span>
                            </td>
                            <td class="hidden md:table-cell py-4 px-5 text-on-surface-variant/80 font-mono text-xs">
                                {{ $fixture->date ? Carbon::parse($fixture->date)->format('d M Y, H:i') : '—' }}
                            </td>
                            <td class="hidden sm:table-cell py-4 px-3 sm:px-5 font-mono text-sm font-bold text-on-surface">
                                @if(in_array($status, [FixtureStatus::Completed, FixtureStatus::Live]))
                                    {{ $fixture->home_score ?? '0' }} – {{ $fixture->away_score ?? '0' }}
                                @else
                                    <span class="text-on-surface-variant/30 text-xs">vs</span>
                                @endif
                            </td>
                            <td class="py-4 px-3 sm:px-5">
                                <span
                                    class="px-2.5 py-0.5 rounded-full font-mono text-[9px] font-bold uppercase tracking-widest {{ $status->badgeClass() }}">
                                    {{ $status->label() }}
                                </span>
                            </td>
                            <td class="py-4 px-3 sm:px-5 text-right space-x-1">
                                <button wire:click="openEdit({{ $fixture->id }})"
                                    class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-on-surface-variant/80 border border-outline-variant/20 bg-surface-container/20 hover:text-primary-container hover:border-primary-container/40 hover:bg-primary-container/10 transition-all duration-150 cursor-pointer"
                                    title="Edit">
                                    <span class="material-symbols-outlined text-[16px]">edit</span>
                                </button>
                                <button wire:click="confirmDelete({{ $fixture->id }})"
                                    class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-on-surface-variant/80 border border-outline-variant/20 bg-surface-container/20 hover:text-error hover:border-error/40 hover:bg-error/10 transition-all duration-150 cursor-pointer"
                                    title="Delete">
                                    <span class="material-symbols-outlined text-[16px]">delete</span>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-16 text-center text-on-surface-variant/40 text-sm font-mono">
                                No fixtures found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-outline-variant/15 px-5 py-3.5 flex items-center justify-between">
            <span class="font-mono text-[10px] text-on-surface-variant/40">{{ $this->fixtures->count() }}
                fixture(s)</span>
            @if($this->search || $this->filterStatus || $this->filterMatchday)
                <button wire:click="$set('search', ''); $set('filterStatus', ''); $set('filterMatchday', 0)"
                    class="font-mono text-[10px] text-primary-container/60 hover:text-primary-container transition-colors cursor-pointer">
                    Clear filters
                </button>
            @endif
        </div>
    </div>

    {{-- ── Add / Edit Modal ─────────────────────────────────────── --}}
    @if($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" x-data
            x-init="$el.classList.add('animate-fade-in')">

            {{-- Backdrop --}}
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" wire:click="closeModal"></div>

            {{-- Panel --}}
            <div class="relative z-10 w-full max-w-lg neo-surface rounded-2xl border border-outline-variant/20 shadow-2xl overflow-hidden"
                @keydown.escape.window="$wire.closeModal()">

                {{-- Modal header --}}
                <div class="flex items-center justify-between px-6 py-4 border-b border-outline-variant/15">
                    <div>
                        <h3 class="font-display font-black text-lg text-on-surface uppercase tracking-tight">
                            {{ $isEditing ? 'Edit Fixture' : 'New Fixture' }}
                        </h3>
                        <p class="text-on-surface-variant/50 text-[10px] font-mono mt-0.5">
                            {{ $isEditing ? 'Update fixture details below.' : 'Fill in the fixture details below.' }}
                        </p>
                    </div>
                    <button wire:click="closeModal"
                        class="w-8 h-8 flex items-center justify-center rounded-lg text-on-surface-variant/60 hover:text-on-surface hover:bg-surface-container/40 transition-all cursor-pointer">
                        <span class="material-symbols-outlined text-[18px]">close</span>
                    </button>
                </div>

                {{-- Modal body --}}
                <div class="px-6 py-5 space-y-4 max-h-[70vh] overflow-y-auto">

                    {{-- Tournament --}}
                    <div class="space-y-1.5">
                        <label
                            class="text-[10px] font-mono font-bold uppercase tracking-widest text-on-surface-variant/60">Tournament</label>
                        <select wire:model="tournament_id"
                            class="w-full px-3.5 py-2.5 rounded-xl bg-surface-container/40 border border-outline-variant/20 text-sm text-on-surface focus:outline-none focus:border-primary-container/50 transition-all font-mono cursor-pointer">
                            <option value="0">Select tournament…</option>
                            @foreach($this->tournaments as $t)
                                <option value="{{ $t->id }}">{{ $t->name }}</option>
                            @endforeach
                        </select>
                        @error('tournament_id')
                            <p class="text-error text-[10px] font-mono">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Teams --}}
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <label
                                class="text-[10px] font-mono font-bold uppercase tracking-widest text-on-surface-variant/60">Home
                                Team</label>
                            <select wire:model="home_team_id"
                                class="w-full px-3.5 py-2.5 rounded-xl bg-surface-container/40 border border-outline-variant/20 text-sm text-on-surface focus:outline-none focus:border-primary-container/50 transition-all font-mono cursor-pointer">
                                <option value="0">Select…</option>
                                @foreach($this->teams as $t)
                                    <option value="{{ $t->id }}">{{ $t->name }}</option>
                                @endforeach
                            </select>
                            @error('home_team_id')
                                <p class="text-error text-[10px] font-mono">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="space-y-1.5">
                            <label
                                class="text-[10px] font-mono font-bold uppercase tracking-widest text-on-surface-variant/60">Away
                                Team</label>
                            <select wire:model="away_team_id"
                                class="w-full px-3.5 py-2.5 rounded-xl bg-surface-container/40 border border-outline-variant/20 text-sm text-on-surface focus:outline-none focus:border-primary-container/50 transition-all font-mono cursor-pointer">
                                <option value="0">Select…</option>
                                @foreach($this->teams as $t)
                                    <option value="{{ $t->id }}">{{ $t->name }}</option>
                                @endforeach
                            </select>
                            @error('away_team_id')
                                <p class="text-error text-[10px] font-mono">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    {{-- Matchday + Date --}}
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <label
                                class="text-[10px] font-mono font-bold uppercase tracking-widest text-on-surface-variant/60">Matchday</label>
                            <input wire:model="matchday" type="number" min="1" max="50"
                                class="w-full px-3.5 py-2.5 rounded-xl bg-surface-container/40 border border-outline-variant/20 text-sm text-on-surface focus:outline-none focus:border-primary-container/50 transition-all font-mono" />
                            @error('matchday')
                                <p class="text-error text-[10px] font-mono">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="space-y-1.5">
                            <label
                                class="text-[10px] font-mono font-bold uppercase tracking-widest text-on-surface-variant/60">Date
                                & Time</label>
                            <input wire:model="date" type="datetime-local"
                                class="w-full px-3.5 py-2.5 rounded-xl bg-surface-container/40 border border-outline-variant/20 text-sm text-on-surface focus:outline-none focus:border-primary-container/50 transition-all font-mono" />
                            @error('date')
                                <p class="text-error text-[10px] font-mono">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    {{-- Status --}}
                    <div class="space-y-1.5">
                        <label
                            class="text-[10px] font-mono font-bold uppercase tracking-widest text-on-surface-variant/60">Status</label>
                        <select wire:model.live="status"
                            class="w-full px-3.5 py-2.5 rounded-xl bg-surface-container/40 border border-outline-variant/20 text-sm text-on-surface focus:outline-none focus:border-primary-container/50 transition-all font-mono cursor-pointer">
                            @foreach($this->statuses as $s)
                                <option value="{{ $s->value }}">{{ $s->label() }}</option>
                            @endforeach
                        </select>
                        @error('status')
                            <p class="text-error text-[10px] font-mono">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Score (only when live or completed) --}}
                    @if(in_array($status, ['live', 'completed']))
                        <div
                            class="grid grid-cols-2 gap-3 p-4 rounded-xl bg-surface-container/20 border border-outline-variant/10">
                            <div class="space-y-1.5">
                                <label
                                    class="text-[10px] font-mono font-bold uppercase tracking-widest text-on-surface-variant/60">Home
                                    Score</label>
                                <input wire:model="home_score" type="number" min="0"
                                    class="w-full px-3.5 py-2.5 rounded-xl bg-surface-container/40 border border-outline-variant/20 text-sm text-on-surface focus:outline-none focus:border-primary-container/50 transition-all font-mono text-center font-bold text-lg" />
                                @error('home_score')
                                    <p class="text-error text-[10px] font-mono">{{ $message }}</p>
                                @enderror
                            </div>
                            <div class="space-y-1.5">
                                <label
                                    class="text-[10px] font-mono font-bold uppercase tracking-widest text-on-surface-variant/60">Away
                                    Score</label>
                                <input wire:model="away_score" type="number" min="0"
                                    class="w-full px-3.5 py-2.5 rounded-xl bg-surface-container/40 border border-outline-variant/20 text-sm text-on-surface focus:outline-none focus:border-primary-container/50 transition-all font-mono text-center font-bold text-lg" />
                                @error('away_score')
                                    <p class="text-error text-[10px] font-mono">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    @endif

                </div>

                {{-- Modal footer --}}
                <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-outline-variant/15">
                    <button wire:click="closeModal"
                        class="px-5 py-2 rounded-xl text-xs font-mono font-bold uppercase tracking-wider text-on-surface-variant border border-outline-variant/20 hover:bg-surface-container/40 transition-all cursor-pointer">
                        Cancel
                    </button>
                    <button wire:click="save" wire:loading.attr="disabled" wire:target="save"
                        class="inline-flex items-center gap-2 px-5 py-2 rounded-xl text-xs font-mono font-bold uppercase tracking-wider text-background bg-primary-container hover:bg-primary-fixed transition-all hover:scale-[1.01] active:scale-[0.99] shadow-lg shadow-primary-container/10 cursor-pointer disabled:opacity-50">
                        <span wire:loading.remove wire:target="save"
                            class="material-symbols-outlined text-[14px]">{{ $isEditing ? 'save' : 'add' }}</span>
                        <span wire:loading wire:target="save"
                            class="material-symbols-outlined text-[14px] animate-spin">progress_activity</span>
                        {{ $isEditing ? 'Update' : 'Create' }}
                    </button>
                </div>

            </div>
        </div>
    @endif

    {{-- ── Delete Confirm Modal ─────────────────────────────────── --}}
    @if($showDeleteModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" wire:click="cancelDelete"></div>
            <div
                class="relative z-10 w-full max-w-sm neo-surface rounded-2xl border border-error/20 shadow-2xl p-6 space-y-4">
                <div class="flex items-center gap-3">
                    <div
                        class="w-10 h-10 rounded-full bg-error/10 border border-error/20 flex items-center justify-center flex-shrink-0">
                        <span class="material-symbols-outlined text-error text-[18px]">warning</span>
                    </div>
                    <div>
                        <h3 class="font-display font-black text-base text-on-surface uppercase tracking-tight">Delete
                            Fixture?</h3>
                        <p class="text-on-surface-variant/50 text-[10px] font-mono mt-0.5">This cannot be undone.</p>
                    </div>
                </div>
                <div class="flex gap-3">
                    <button wire:click="cancelDelete"
                        class="flex-1 px-4 py-2 rounded-xl text-xs font-mono font-bold uppercase tracking-wider text-on-surface-variant border border-outline-variant/20 hover:bg-surface-container/40 transition-all cursor-pointer">
                        Cancel
                    </button>
                    <button wire:click="delete" wire:loading.attr="disabled" wire:target="delete"
                        class="flex-1 px-4 py-2 rounded-xl text-xs font-mono font-bold uppercase tracking-wider text-white bg-error hover:bg-error/90 transition-all cursor-pointer disabled:opacity-50">
                        Delete
                    </button>
                </div>
            </div>
        </div>
    @endif

</div>