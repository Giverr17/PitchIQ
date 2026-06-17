<?php

use App\Models\TokenCost;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.admin')] class extends Component
{
    public array $costs = [];
    public string $toast     = '';
    public string $toastType = 'success';

    public function mount(): void
    {
        $this->loadCosts();
    }

    private function loadCosts(): void
    {
        $this->costs = TokenCost::orderBy('feature')->get()->keyBy('feature')->map(fn($row) => [
            'id'          => $row->id,
            'feature'     => $row->feature,
            'label'       => $row->label,
            'description' => $row->description,
            'cost'        => $row->cost,
        ])->toArray();
    }

    public function save(): void
    {
        foreach ($this->costs as $feature => $data) {
            $this->validate([
                "costs.{$feature}.cost" => 'required|integer|min:0|max:9999',
            ], [
                "costs.{$feature}.cost.min"     => "Cost for {$data['label']} cannot be negative.",
                "costs.{$feature}.cost.max"     => "Cost for {$data['label']} cannot exceed 9999.",
                "costs.{$feature}.cost.integer" => "Cost for {$data['label']} must be a whole number.",
            ]);
        }

        foreach ($this->costs as $feature => $data) {
            TokenCost::where('feature', $feature)->update(['cost' => (int) $data['cost']]);
        }

        $this->toast     = 'Token costs updated successfully.';
        $this->toastType = 'success';
    }

    public function dismissToast(): void
    {
        $this->toast = '';
    }
}
?>

@section('title', 'Token Costs — Admin')

<div class="max-w-3xl mx-auto px-5 sm:px-8 py-10"
     x-data="{ toast: @entangle('toast'), toastType: @entangle('toastType') }">

    {{-- Toast --}}
    <div
        x-show="toast !== ''"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 -translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-end="opacity-0"
        x-init="$watch('toast', v => { if(v) setTimeout(() => $wire.dismissToast(), 4000) })"
        class="fixed top-6 right-6 z-50 max-w-sm w-full">
        <div :class="toastType === 'success'
                ? 'bg-surface-container border-primary-container/40 text-primary-container'
                : 'bg-surface-container border-error/40 text-error'"
             class="flex items-center gap-3 px-5 py-3.5 rounded-2xl border shadow-2xl font-mono text-xs font-semibold">
            <span class="material-symbols-outlined text-[18px]" x-text="toastType === 'success' ? 'check_circle' : 'error'"></span>
            <span x-text="toast" class="flex-1"></span>
            <button wire:click="dismissToast" class="opacity-60 hover:opacity-100 cursor-pointer">&times;</button>
        </div>
    </div>

    {{-- Header --}}
    <div class="mb-8">
        <div class="flex items-center gap-3 mb-2">
            <div class="w-10 h-10 rounded-xl bg-primary-container/15 border border-primary-container/30 flex items-center justify-center">
                <span class="material-symbols-outlined text-primary-container text-[22px]">token</span>
            </div>
            <div>
                <h1 class="font-display font-black text-2xl text-on-surface uppercase tracking-tight">
                    Token <span class="text-primary-container">Costs</span>
                </h1>
                <p class="font-mono text-xs text-on-surface-variant/60">Set how many tokens each feature costs users.</p>
            </div>
        </div>
    </div>

    {{-- Costs Table --}}
    <div class="neo-surface rounded-2xl border border-outline-variant/15 overflow-hidden mb-6">
        <div class="hidden sm:block px-5 py-3 border-b border-outline-variant/10 bg-surface-container/40">
            <div class="grid grid-cols-12 gap-4">
                <span class="col-span-5 font-mono text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/50">Feature</span>
                <span class="col-span-5 font-mono text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/50">When Charged</span>
                <span class="col-span-2 font-mono text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/50 text-right">Cost</span>
            </div>
        </div>

        <div class="divide-y divide-outline-variant/8">
            @foreach($costs as $feature => $row)
                <div class="px-5 py-5 flex flex-col sm:grid sm:grid-cols-12 gap-3 sm:gap-4 sm:items-center" wire:key="cost-{{ $feature }}">
                    <div class="sm:col-span-5">
                        <p class="font-mono text-sm font-bold text-on-surface">{{ $row['label'] }}</p>
                        <p class="font-mono text-[10px] text-on-surface-variant/50 mt-0.5">{{ $row['description'] }}</p>
                    </div>
                    <div class="sm:col-span-5">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-surface-container border border-outline-variant/20">
                            <span class="material-symbols-outlined text-[12px] text-on-surface-variant/50">
                                @if($feature === 'prediction') sports_score
                                @elseif($feature === 'squad_builder') group
                                @else sports_esports
                                @endif
                            </span>
                            <span class="font-mono text-[10px] text-on-surface-variant/70">
                                @if($feature === 'prediction') Per fixture prediction submitted
                                @elseif($feature === 'squad_builder') On first squad creation per tournament
                                @else Per game entry
                                @endif
                            </span>
                        </span>
                    </div>
                    <div class="sm:col-span-2 flex items-center sm:justify-end gap-2">
                        <span class="font-mono text-[10px] text-on-surface-variant/50 sm:hidden">Cost:</span>
                        <div class="relative">
                            <input
                                type="number"
                                min="0"
                                max="9999"
                                wire:model="costs.{{ $feature }}.cost"
                                class="w-20 text-center px-3 py-2 rounded-xl border font-mono text-sm font-bold
                                       bg-surface-container border-outline-variant/30 text-on-surface
                                       focus:outline-none focus:border-primary-container/60 focus:bg-surface-container-high
                                       transition-colors [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none">
                        </div>
                        <span class="text-base" title="tokens">🪙</span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Info box --}}
    <div class="mb-6 flex items-start gap-3 p-4 rounded-2xl bg-surface-container/50 border border-outline-variant/15">
        <span class="material-symbols-outlined text-on-surface-variant/40 text-[18px] flex-shrink-0 mt-0.5">info</span>
        <p class="font-mono text-[11px] text-on-surface-variant/60 leading-relaxed">
            Changes take effect immediately on the next user action. A cost of <strong class="text-on-surface">0</strong> means the feature is free.
            Users start with <strong class="text-on-surface">20 tokens</strong> by default (set in the users migration).
        </p>
    </div>

    {{-- Save --}}
    <div class="flex justify-end">
        <button
            wire:click="save"
            wire:loading.attr="disabled"
            class="inline-flex items-center gap-2 px-6 py-3 rounded-xl font-mono text-sm font-bold uppercase tracking-wider
                   bg-primary-container text-background hover:bg-primary-fixed transition-all duration-200
                   hover:scale-[1.02] active:scale-[0.98] shadow-lg shadow-primary-container/20
                   disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer">
            <span class="material-symbols-outlined text-[18px]" wire:loading.class="animate-spin" wire:loading.class.remove="text-[18px]" wire:target="save">save</span>
            <span wire:loading.remove wire:target="save">Save Changes</span>
            <span wire:loading wire:target="save">Saving…</span>
        </button>
    </div>

</div>
