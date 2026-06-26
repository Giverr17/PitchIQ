<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;
use App\Models\User;

new #[Layout('layouts.admin')] class extends Component {

    use WithPagination;

    public string $search = '';
    public string $facultyFilter = '';

    // Detail modal
    public bool $showDetail = false;
    public ?int $selectedId = null;
    public array $detail = [];

    // Guarded phone correction (payout troubleshooting) — the ONLY editable field
    public string $editPhone = '';
    public string $phoneStatus = '';

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedFacultyFilter(): void { $this->resetPage(); }

    #[Computed]
    public function users()
    {
        return User::query()
            ->withCount('fantasyTeams')
            ->when($this->search, function ($q) {
                $term = "%{$this->search}%";
                $q->where(fn($w) => $w
                    ->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('referral_code', 'like', $term));
            })
            ->when($this->facultyFilter, fn($q) => $q->where('faculty', $this->facultyFilter))
            ->latest()
            ->paginate(20);
    }

    public function openDetail(int $id): void
    {
        $user = User::withCount(['fantasyTeams', 'predictions', 'referredUsers', 'airtimePayouts'])
            ->with('referrer:id,name')
            ->findOrFail($id);

        $this->selectedId = $user->id;
        $this->editPhone = $user->phone ?? '';
        $this->phoneStatus = '';
        $this->resetErrorBag();

        $this->detail = [
            'name'             => $user->name,
            'email'            => $user->email,
            'phone'            => $user->phone,
            'faculty'          => $user->faculty,
            'tokens'           => $user->tokens,
            'referral_code'    => $user->referral_code,
            'referred_by'      => $user->referrer?->name,
            'is_admin'         => (bool) $user->is_admin,
            'joined'           => $user->created_at?->format('d M Y, H:i'),
            'squads'           => $user->fantasy_teams_count,
            'predictions'      => $user->predictions_count,
            'referrals_made'   => $user->referred_users_count,
            'payouts'          => $user->airtime_payouts_count,
        ];

        $this->showDetail = true;
    }

    public function savePhone(): void
    {
        if (!$this->selectedId) {
            return;
        }

        $validated = $this->validate([
            'editPhone' => ['nullable', 'string', 'regex:' . User::PHONE_REGEX],
        ], [
            'editPhone.regex' => 'Enter a valid Nigerian phone number, e.g. 08031234567.',
        ]);

        // Only the phone is ever written — nothing else.
        $user = User::findOrFail($this->selectedId);
        $user->update(['phone' => $validated['editPhone'] ?: null]);

        $this->detail['phone'] = $user->phone;
        $this->phoneStatus = 'Phone updated.';
        unset($this->users);
    }

    public function closeDetail(): void
    {
        $this->showDetail = false;
        $this->selectedId = null;
        $this->detail = [];
    }
}; ?>

<div class="space-y-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="font-display font-black text-2xl text-on-surface uppercase tracking-tight">Users</h2>
            <p class="text-on-surface-variant/60 text-xs mt-1 font-mono">Registered players — view details and correct payout phone numbers.</p>
        </div>
        <span class="font-mono text-[10px] text-on-surface-variant/40">{{ $this->users->total() }} user(s)</span>
    </div>

    {{-- Filters --}}
    <div class="flex flex-col sm:flex-row gap-3">
        <div class="relative flex-1">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant/40 text-[16px]">search</span>
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search name, email, phone, referral code..."
                   class="w-full pl-9 pr-4 py-2.5 rounded-xl bg-white/5 border border-outline-variant/20 text-sm text-on-surface placeholder:text-on-surface-variant/30 focus:outline-none focus:border-[#00E676]/40 transition-all font-mono" />
        </div>
        <select wire:model.live="facultyFilter"
            class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-[#0d110f] border border-outline-variant/20 text-sm text-on-surface-variant font-mono focus:outline-none focus:border-[#00E676]/40 transition-all cursor-pointer">
            <option value="">All Faculties</option>
            @foreach(config('faculties') as $f)
                <option value="{{ $f }}">{{ $f }}</option>
            @endforeach
        </select>
    </div>

    {{-- Table --}}
    <div class="rounded-2xl overflow-hidden border border-outline-variant/15" style="background: rgba(13,17,15,0.8);">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[640px]">
                <thead>
                    <tr class="border-b border-outline-variant/15" style="background: rgba(255,255,255,0.02);">
                        <th class="py-3.5 px-5 text-xs font-semibold text-on-surface-variant uppercase tracking-wider font-mono">Name</th>
                        <th class="py-3.5 px-5 text-xs font-semibold text-on-surface-variant uppercase tracking-wider font-mono">Email</th>
                        <th class="hidden sm:table-cell py-3.5 px-5 text-xs font-semibold text-on-surface-variant uppercase tracking-wider font-mono">Phone</th>
                        <th class="hidden md:table-cell py-3.5 px-5 text-xs font-semibold text-on-surface-variant uppercase tracking-wider font-mono">Faculty</th>
                        <th class="py-3.5 px-5 text-xs font-semibold text-on-surface-variant uppercase tracking-wider font-mono text-right">Tokens</th>
                        <th class="hidden md:table-cell py-3.5 px-5 text-xs font-semibold text-on-surface-variant uppercase tracking-wider font-mono text-right">Squads</th>
                        <th class="hidden sm:table-cell py-3.5 px-5 text-xs font-semibold text-on-surface-variant uppercase tracking-wider font-mono">Joined</th>
                        <th class="py-3.5 px-5 text-xs font-semibold text-on-surface-variant uppercase tracking-wider font-mono text-right">Details</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->users as $user)
                        <tr class="border-b border-outline-variant/10 text-sm hover:bg-white/[0.02] transition-all duration-150">
                            <td class="py-4 px-5 font-bold text-on-surface max-w-[160px]"><span class="block truncate">{{ $user->name }}</span></td>
                            <td class="py-4 px-5 text-on-surface-variant/80 font-mono text-xs max-w-[200px]"><span class="block truncate">{{ $user->email }}</span></td>
                            <td class="hidden sm:table-cell py-4 px-5 text-on-surface-variant/80 font-mono text-xs">{{ $user->phone ?? '—' }}</td>
                            <td class="hidden md:table-cell py-4 px-5 text-on-surface-variant/70 font-mono text-xs">{{ $user->faculty ?? '—' }}</td>
                            <td class="py-4 px-5 text-right font-mono text-xs font-bold" style="color:#00E676;">{{ $user->tokens }}</td>
                            <td class="hidden md:table-cell py-4 px-5 text-right font-mono text-xs text-on-surface-variant/80">{{ $user->fantasy_teams_count }}</td>
                            <td class="hidden sm:table-cell py-4 px-5 text-on-surface-variant/60 font-mono text-[11px]">{{ $user->created_at?->format('d M Y') }}</td>
                            <td class="py-4 px-5 text-right">
                                <button wire:click="openDetail({{ $user->id }})"
                                    class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-on-surface-variant/80 border border-outline-variant/20 bg-white/5 hover:text-[#00E676] hover:border-[#00E676]/40 hover:bg-[#00E676]/10 transition-all duration-150 cursor-pointer"
                                    title="View details">
                                    <span class="material-symbols-outlined text-[16px]">visibility</span>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-16 text-center">
                                <span class="material-symbols-outlined text-4xl text-on-surface-variant/20 block mb-3">group</span>
                                <p class="text-on-surface-variant/40 text-sm font-mono">No users found.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-outline-variant/15 px-5 py-3.5">
            {{ $this->users->links() }}
        </div>
    </div>

    {{-- DETAIL MODAL --}}
    @if($showDetail)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background: rgba(0,0,0,0.7); backdrop-filter: blur(4px);">
            <div class="w-full max-w-lg rounded-2xl border border-outline-variant/20 shadow-2xl" style="background: #0d110f;">

                {{-- Header --}}
                <div class="flex items-center justify-between px-6 py-4 border-b border-outline-variant/15">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-full flex items-center justify-center font-black text-sm text-black flex-shrink-0"
                             style="background: linear-gradient(135deg, #00E676 0%, #00b359 100%);">
                            {{ strtoupper(substr($detail['name'] ?? '?', 0, 1)) }}
                        </div>
                        <div>
                            <h3 class="font-display font-black text-sm uppercase tracking-wider text-on-surface">{{ $detail['name'] ?? '' }}</h3>
                            @if($detail['is_admin'] ?? false)
                                <span class="font-mono text-[9px] uppercase tracking-widest text-primary-container">Admin</span>
                            @endif
                        </div>
                    </div>
                    <button wire:click="closeDetail" class="text-on-surface-variant hover:text-white transition-colors">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                {{-- Body --}}
                <div class="px-6 py-5 space-y-5 max-h-[70vh] overflow-y-auto">

                    {{-- Read-only fields --}}
                    <div class="grid grid-cols-2 gap-x-4 gap-y-3 font-mono text-xs">
                        <div>
                            <p class="text-on-surface-variant/40 uppercase tracking-wider text-[9px] mb-0.5">Email</p>
                            <p class="text-on-surface break-all">{{ $detail['email'] ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-on-surface-variant/40 uppercase tracking-wider text-[9px] mb-0.5">Faculty</p>
                            <p class="text-on-surface">{{ $detail['faculty'] ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-on-surface-variant/40 uppercase tracking-wider text-[9px] mb-0.5">Tokens</p>
                            <p class="font-bold" style="color:#00E676;">{{ $detail['tokens'] ?? 0 }}</p>
                        </div>
                        <div>
                            <p class="text-on-surface-variant/40 uppercase tracking-wider text-[9px] mb-0.5">Referral Code</p>
                            <p class="text-on-surface">{{ $detail['referral_code'] ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-on-surface-variant/40 uppercase tracking-wider text-[9px] mb-0.5">Referred By</p>
                            <p class="text-on-surface">{{ $detail['referred_by'] ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-on-surface-variant/40 uppercase tracking-wider text-[9px] mb-0.5">Joined</p>
                            <p class="text-on-surface">{{ $detail['joined'] ?? '—' }}</p>
                        </div>
                    </div>

                    {{-- Activity counts --}}
                    <div class="grid grid-cols-4 gap-2">
                        @foreach([
                            ['label' => 'Squads', 'val' => $detail['squads'] ?? 0],
                            ['label' => 'Predictions', 'val' => $detail['predictions'] ?? 0],
                            ['label' => 'Referrals', 'val' => $detail['referrals_made'] ?? 0],
                            ['label' => 'Payouts', 'val' => $detail['payouts'] ?? 0],
                        ] as $stat)
                            <div class="rounded-xl border border-outline-variant/15 p-3 text-center" style="background:rgba(255,255,255,0.02);">
                                <p class="font-mono font-black text-lg text-on-surface">{{ $stat['val'] }}</p>
                                <p class="font-mono text-[8px] uppercase tracking-wider text-on-surface-variant/50 mt-0.5">{{ $stat['label'] }}</p>
                            </div>
                        @endforeach
                    </div>

                    {{-- Phone — the only editable field --}}
                    <div class="rounded-xl border border-[#00E676]/20 p-4" style="background:rgba(0,230,118,0.03);">
                        <label class="block text-[10px] font-mono font-bold uppercase tracking-widest text-on-surface-variant/60 mb-2">
                            Payout Phone <span class="text-on-surface-variant/40 normal-case">(correctable)</span>
                        </label>
                        <div class="flex items-center gap-2">
                            <input wire:model="editPhone" type="tel" placeholder="e.g. 08031234567"
                                   class="flex-1 px-3.5 py-2.5 rounded-xl text-sm text-white border border-outline-variant/20 bg-[#0d110f] focus:outline-none focus:border-[#00E676]/50 transition-all font-mono" />
                            <button wire:click="savePhone" wire:loading.attr="disabled" wire:target="savePhone"
                                class="px-4 py-2.5 rounded-xl text-xs font-mono font-bold uppercase tracking-wider text-black cursor-pointer disabled:opacity-50 flex-shrink-0"
                                style="background:linear-gradient(135deg,#00E676 0%,#00b359 100%);">
                                Save
                            </button>
                        </div>
                        @error('editPhone') <p class="text-red-400 text-[10px] font-mono mt-1.5">{{ $message }}</p> @enderror
                        @if($phoneStatus) <p class="text-[10px] font-mono mt-1.5" style="color:#00E676;">{{ $phoneStatus }}</p> @endif
                    </div>
                </div>

                {{-- Footer --}}
                <div class="px-6 py-4 border-t border-outline-variant/15 flex items-center justify-end">
                    <button wire:click="closeDetail"
                        class="px-5 py-2 rounded-xl text-xs font-mono font-bold uppercase tracking-wider text-on-surface-variant border border-outline-variant/20 hover:bg-white/5 transition-all cursor-pointer">
                        Close
                    </button>
                </div>
            </div>
        </div>
    @endif

</div>
