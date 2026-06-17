<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithFileUploads;
use App\Models\CampusAd;

new #[Layout('layouts.admin')] class extends Component {
    use WithFileUploads;

    public $ads = [];

    // Form
    public string $business_name = '';
    public $image;                 // uploaded file
    public string $link_url = '';
    public string $starts_at = '';
    public string $ends_at = '';

    public bool $showModal = false;
    public ?int $editingId = null;
    public string $currentImagePath = '';

    public function mount(): void
    {
        $this->loadAds();
    }

    public function loadAds(): void
    {
        $this->ads = CampusAd::latest()->get()->toArray();
    }

    public function openCreate(): void
    {
        $this->reset(['business_name', 'image', 'link_url', 'starts_at', 'ends_at', 'editingId', 'currentImagePath']);
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate([
            'business_name' => 'required|string|max:100',
            'image'         => $this->editingId ? 'nullable|image|max:2048' : 'required|image|max:2048',
            'link_url'      => 'nullable|url',
            'starts_at'     => 'nullable|date',
            'ends_at'       => 'nullable|date|after_or_equal:starts_at',
        ]);

        $data = [
            'business_name' => $this->business_name,
            'link_url'      => $this->link_url ?: null,
            'starts_at'     => $this->starts_at ?: null,
            'ends_at'       => $this->ends_at ?: null,
        ];

        // Store uploaded image if a new one was provided
        if ($this->image) {
            $data['image_path'] = $this->image->store('campus-ads', 'public');
        }

        if ($this->editingId) {
            CampusAd::findOrFail($this->editingId)->update($data);
        } else {
            CampusAd::create($data);
        }

        $this->showModal = false;
        $this->loadAds();
    }

    public function toggleActive(int $id): void
    {
        $ad = CampusAd::findOrFail($id);
        $ad->update(['is_active' => !$ad->is_active]);
        $this->loadAds();
    }

    public function delete(int $id): void
    {
        CampusAd::findOrFail($id)->delete();
        $this->loadAds();
    }

    public function openEdit(int $id): void
    {
        $ad = CampusAd::findOrFail($id);
        $this->editingId          = $id;
        $this->business_name      = $ad->business_name;
        $this->link_url           = $ad->link_url ?? '';
        $this->starts_at          = $ad->starts_at?->format('Y-m-d') ?? '';
        $this->ends_at            = $ad->ends_at?->format('Y-m-d') ?? '';
        $this->currentImagePath   = $ad->image_path ?? '';
        $this->image              = null;
        $this->showModal          = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }
} ?>

<div class="space-y-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="font-display font-black text-2xl text-on-surface uppercase tracking-tight">Campus Ads</h2>
            <p class="text-on-surface-variant/60 text-xs mt-1 font-mono">Paid banner placements from campus businesses.</p>
        </div>
        <button wire:click="openCreate"
                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs font-mono font-bold uppercase tracking-wider text-black transition-all hover:scale-[1.01] cursor-pointer"
                style="background: linear-gradient(135deg, #00E676 0%, #00b359 100%);">
            <span class="material-symbols-outlined text-[16px]">add</span>
            New Ad
        </button>
    </div>

    {{-- Ads grid --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse($ads as $ad)
            <div class="rounded-2xl border border-outline-variant/15 overflow-hidden" style="background: rgba(13,17,15,0.8);">
                {{-- Banner preview --}}
                <div class="h-32 bg-white/5 relative">
                    <img src="{{ asset('storage/' . $ad['image_path']) }}" alt="{{ $ad['business_name'] }}"
                         loading="lazy" class="w-full h-full object-cover" />
                    <span class="absolute top-2 right-2 px-2 py-0.5 rounded-full font-mono text-[9px] font-bold uppercase tracking-widest border"
                          style="{{ $ad['is_active']
                              ? 'background:rgba(0,230,118,0.1);color:#00E676;border-color:rgba(0,230,118,0.25);'
                              : 'background:rgba(180,180,180,0.1);color:#9ca3af;border-color:rgba(180,180,180,0.2);' }}">
                        {{ $ad['is_active'] ? 'Active' : 'Paused' }}
                    </span>
                </div>

                <div class="p-4">
                    <h3 class="font-bold text-white text-sm">{{ $ad['business_name'] }}</h3>
                    <p class="font-mono text-[10px] text-on-surface-variant/40 mt-1">
                        {{ $ad['clicks'] }} clicks ·
                        {{ $ad['ends_at'] ? 'ends ' . \Carbon\Carbon::parse($ad['ends_at'])->format('d M') : 'no end date' }}
                    </p>

                    <div class="flex items-center gap-2 mt-3">
                        <button wire:click="openEdit({{ $ad['id'] }})"
                                class="flex-1 py-1.5 rounded-lg text-[10px] font-mono font-bold uppercase tracking-wider border border-outline-variant/20 text-on-surface-variant hover:text-white hover:border-white/30 transition-all cursor-pointer">
                            Edit
                        </button>
                        <button wire:click="toggleActive({{ $ad['id'] }})"
                                class="flex-1 py-1.5 rounded-lg text-[10px] font-mono font-bold uppercase tracking-wider border border-outline-variant/20 text-on-surface-variant hover:text-white transition-all cursor-pointer">
                            {{ $ad['is_active'] ? 'Pause' : 'Activate' }}
                        </button>
                        <button wire:click="delete({{ $ad['id'] }})"
                                wire:confirm="Delete this ad?"
                                class="px-3 py-1.5 rounded-lg text-[10px] font-mono font-bold uppercase tracking-wider border border-outline-variant/20 text-on-surface-variant/60 hover:text-red-400 hover:border-red-400/40 transition-all cursor-pointer">
                            <span class="material-symbols-outlined text-[13px]">delete</span>
                        </button>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full rounded-2xl border border-outline-variant/15 p-12 text-center" style="background: rgba(13,17,15,0.8);">
                <span class="material-symbols-outlined text-4xl text-on-surface-variant/20 block mb-3">storefront</span>
                <p class="font-mono text-xs text-on-surface-variant/40">No campus ads yet.</p>
            </div>
        @endforelse
    </div>

    {{-- MODAL --}}
    @if($showModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background: rgba(0,0,0,0.7); backdrop-filter: blur(4px);">
        <div class="w-full max-w-md rounded-2xl border border-outline-variant/20 shadow-2xl" style="background: #0d110f;">
            <div class="flex items-center justify-between px-6 py-4 border-b border-outline-variant/15">
                <h3 class="font-display font-black text-sm uppercase tracking-wider text-on-surface">
                    {{ $editingId ? 'Edit Ad' : 'New Campus Ad' }}
                </h3>
                <button wire:click="closeModal" class="text-on-surface-variant hover:text-white"><span class="material-symbols-outlined text-[20px]">close</span></button>
            </div>

            <div class="px-6 py-5 space-y-4">
                <div>
                    <label class="block text-xs font-mono text-on-surface-variant uppercase tracking-wider mb-1.5">Business Name</label>
                    <input wire:model="business_name" type="text" placeholder="e.g. Mama Nkechi Kitchen"
                           class="w-full px-4 py-2.5 rounded-xl text-sm text-white placeholder-on-surface-variant/30 border border-outline-variant/20 bg-white/5 focus:outline-none focus:border-[#00E676]/50 transition-all" />
                    @error('business_name') <p class="text-red-400 text-xs font-mono mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-mono text-on-surface-variant uppercase tracking-wider mb-1.5">
                        Banner Image
                        @if($editingId)<span class="normal-case font-normal text-on-surface-variant/40 ml-1">(optional — leave blank to keep current)</span>@endif
                    </label>
                    <input wire:model="image" type="file" accept="image/*"
                           class="w-full text-xs text-on-surface-variant file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-[#00E676]/10 file:text-[#00E676] cursor-pointer" />
                    <div wire:loading wire:target="image" class="font-mono text-[10px] text-on-surface-variant/40 mt-1">Uploading...</div>
                    @error('image') <p class="text-red-400 text-xs font-mono mt-1">{{ $message }}</p> @enderror
                    @if($image)
                        <img src="{{ $image->temporaryUrl() }}" loading="lazy" class="mt-2 h-24 w-full object-cover rounded-lg" />
                    @elseif($currentImagePath)
                        <div class="mt-2 relative rounded-lg overflow-hidden">
                            <img src="{{ asset('storage/' . $currentImagePath) }}" loading="lazy" class="h-24 w-full object-cover opacity-60" />
                            <div class="absolute inset-0 flex items-center justify-center"
                                 style="background: rgba(0,0,0,0.35);">
                                <span class="font-mono text-[9px] text-white/70 uppercase tracking-widest">Current image · leave blank to keep</span>
                            </div>
                        </div>
                    @endif
                </div>

                <div>
                    <label class="block text-xs font-mono text-on-surface-variant uppercase tracking-wider mb-1.5">Link URL <span class="text-on-surface-variant/40">(optional)</span></label>
                    <input wire:model="link_url" type="url" placeholder="https://..."
                           class="w-full px-4 py-2.5 rounded-xl text-sm text-white placeholder-on-surface-variant/30 border border-outline-variant/20 bg-white/5 focus:outline-none focus:border-[#00E676]/50 transition-all" />
                    @error('link_url') <p class="text-red-400 text-xs font-mono mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-mono text-on-surface-variant uppercase tracking-wider mb-1.5">Starts</label>
                        <input wire:model="starts_at" type="date"
                               class="w-full px-4 py-2.5 rounded-xl text-sm text-white border border-outline-variant/20 bg-white/5 focus:outline-none focus:border-[#00E676]/50 transition-all" />
                    </div>
                    <div>
                        <label class="block text-xs font-mono text-on-surface-variant uppercase tracking-wider mb-1.5">Ends</label>
                        <input wire:model="ends_at" type="date"
                               class="w-full px-4 py-2.5 rounded-xl text-sm text-white border border-outline-variant/20 bg-white/5 focus:outline-none focus:border-[#00E676]/50 transition-all" />
                        @error('ends_at') <p class="text-red-400 text-xs font-mono mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <div class="px-6 py-4 border-t border-outline-variant/15 flex items-center justify-end gap-3">
                <button wire:click="closeModal" class="px-5 py-2 rounded-xl text-xs font-mono font-bold uppercase tracking-wider text-on-surface-variant border border-outline-variant/20 hover:bg-white/5 transition-all cursor-pointer">Cancel</button>
                <button wire:click="save" wire:loading.attr="disabled" wire:target="save,image"
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