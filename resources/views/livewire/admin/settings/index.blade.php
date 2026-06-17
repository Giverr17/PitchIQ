<?php

use App\Models\AppSetting;
use App\Models\Player;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.admin')] class extends Component
{
    public string $fantasyBudget = '600';
    public string $toast         = '';
    public string $toastType     = 'success';

    // Stats shown to help admin set a sensible budget
    public int $avgPlayerPrice = 0;
    public int $minSquadCost   = 0;
    public int $maxSquadCost   = 0;

    public function mount(): void
    {
        $this->fantasyBudget = AppSetting::get(AppSetting::FANTASY_BUDGET, '600');
        $this->computePlayerStats();
    }

    private function computePlayerStats(): void
    {
        $prices = Player::pluck('fantasy_price');
        if ($prices->isEmpty()) return;

        $this->avgPlayerPrice = (int) round($prices->average());

        // Minimum squad cost: cheapest 11 players
        $this->minSquadCost = (int) $prices->sort()->take(11)->sum();

        // Maximum squad cost: most expensive 11 players
        $this->maxSquadCost = (int) $prices->sortDesc()->take(11)->sum();
    }

    public function save(): void
    {
        $this->validate([
            'fantasyBudget' => 'required|integer|min:1|max:99999',
        ]);

        AppSetting::where('key', AppSetting::FANTASY_BUDGET)
            ->update(['value' => (string) (int) $this->fantasyBudget]);

        $this->toast     = 'Settings saved.';
        $this->toastType = 'success';
    }

    public function dismissToast(): void
    {
        $this->toast = '';
    }
}
?>

@section('title', 'Settings — Admin')

<div class="max-w-2xl mx-auto px-5 sm:px-8 py-10"
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
                <span class="material-symbols-outlined text-primary-container text-[22px]">tune</span>
            </div>
            <div>
                <h1 class="font-display font-black text-2xl text-on-surface uppercase tracking-tight">
                    App <span class="text-primary-container">Settings</span>
                </h1>
                <p class="font-mono text-xs text-on-surface-variant/60">Global configuration for game mechanics.</p>
            </div>
        </div>
    </div>

    {{-- Fantasy Budget Section --}}
    <div class="neo-surface rounded-2xl border border-outline-variant/15 overflow-hidden mb-6">
        <div class="px-5 py-3.5 border-b border-outline-variant/10 bg-surface-container/40">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-[16px] text-secondary-container">group</span>
                <span class="font-mono text-xs font-bold uppercase tracking-widest text-on-surface-variant/70">Fantasy Squad Builder</span>
            </div>
        </div>

        <div class="p-6 space-y-6">

            {{-- Budget input --}}
            <div>
                <label class="block font-mono text-xs font-bold text-on-surface uppercase tracking-wider mb-1">
                    Squad Budget Cap
                </label>
                <p class="font-mono text-[11px] text-on-surface-variant/50 mb-3">
                    Maximum total player price a user can spend when building a squad for a fixture.
                    Lower values force harder strategic choices.
                </p>
                <div class="flex items-center gap-3">
                    <input
                        type="number"
                        min="1"
                        max="99999"
                        wire:model="fantasyBudget"
                        class="w-32 text-center px-4 py-2.5 rounded-xl border font-mono text-lg font-bold
                               bg-surface-container border-outline-variant/30 text-on-surface
                               focus:outline-none focus:border-primary-container/60
                               transition-colors [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none">
                    <span class="font-mono text-xs text-on-surface-variant/50">coins</span>
                </div>
                @error('fantasyBudget') <p class="text-red-400 text-xs font-mono mt-2">{{ $message }}</p> @enderror
            </div>

            {{-- Player price reference --}}
            @if($avgPlayerPrice > 0)
                <div class="rounded-xl bg-surface-container/50 border border-outline-variant/15 p-4 space-y-3">
                    <p class="font-mono text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/50">Current Player Price Reference</p>
                    <div class="grid grid-cols-3 gap-3">
                        <div class="text-center">
                            <span class="block font-mono text-lg font-black text-on-surface">{{ $minSquadCost }}</span>
                            <span class="font-mono text-[9px] text-on-surface-variant/50 uppercase tracking-wider">Min squad<br>(11 cheapest)</span>
                        </div>
                        <div class="text-center">
                            <span class="block font-mono text-lg font-black text-primary-container">{{ $avgPlayerPrice * 11 }}</span>
                            <span class="font-mono text-[9px] text-on-surface-variant/50 uppercase tracking-wider">Avg squad<br>(avg × 11)</span>
                        </div>
                        <div class="text-center">
                            <span class="block font-mono text-lg font-black text-on-surface">{{ $maxSquadCost }}</span>
                            <span class="font-mono text-[9px] text-on-surface-variant/50 uppercase tracking-wider">Max squad<br>(11 priciest)</span>
                        </div>
                    </div>
                    <p class="font-mono text-[10px] text-on-surface-variant/40 leading-relaxed">
                        Set budget between min and avg squad cost for tight strategic games.
                        Current avg player price: <strong class="text-on-surface">{{ $avgPlayerPrice }}</strong>
                    </p>
                </div>
            @endif
        </div>
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
            <span wire:loading.remove wire:target="save">Save Settings</span>
            <span wire:loading wire:target="save">Saving…</span>
        </button>
    </div>

</div>
