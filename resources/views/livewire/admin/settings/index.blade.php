<?php

use App\Models\AppSetting;
use App\Models\Player;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.admin')] class extends Component
{
    public string $fantasyBudget  = '600';   // 11-a-side
    public string $fantasyBudget5 = '320';   // 5-a-side
    public string $toast          = '';
    public string $toastType      = 'success';

    // Stats shown to help admin set a sensible budget
    public int $avgPlayerPrice = 0;
    public int $minSquadCost   = 0;   // cheapest 11
    public int $maxSquadCost   = 0;   // priciest 11
    public int $minSquadCost5  = 0;   // cheapest 5
    public int $maxSquadCost5  = 0;   // priciest 5

    public function mount(): void
    {
        $this->fantasyBudget  = AppSetting::get(AppSetting::FANTASY_BUDGET, '600');
        $this->fantasyBudget5 = AppSetting::get(AppSetting::FANTASY_BUDGET_5, '320');
        $this->computePlayerStats();
    }

    private function computePlayerStats(): void
    {
        $prices = Player::pluck('fantasy_price');
        if ($prices->isEmpty()) return;

        $this->avgPlayerPrice = (int) round($prices->average());

        // 11-a-side: cheapest / priciest 11 players
        $this->minSquadCost = (int) $prices->sort()->take(11)->sum();
        $this->maxSquadCost = (int) $prices->sortDesc()->take(11)->sum();

        // 5-a-side: cheapest / priciest 5 players
        $this->minSquadCost5 = (int) $prices->sort()->take(5)->sum();
        $this->maxSquadCost5 = (int) $prices->sortDesc()->take(5)->sum();
    }

    public function save(): void
    {
        $this->validate([
            'fantasyBudget'  => 'required|integer|min:1|max:99999',
            'fantasyBudget5' => 'required|integer|min:1|max:99999',
        ]);

        AppSetting::updateOrCreate(
            ['key' => AppSetting::FANTASY_BUDGET],
            ['value' => (string) (int) $this->fantasyBudget],
        );
        AppSetting::updateOrCreate(
            ['key' => AppSetting::FANTASY_BUDGET_5],
            ['value' => (string) (int) $this->fantasyBudget5],
        );

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

            {{-- Budget inputs: 11-a-side + 5-a-side --}}
            <p class="font-mono text-[11px] text-on-surface-variant/50 -mt-1">
                Maximum total player price a user can spend when building a squad for a fixture.
                Each squad size has its own cap. Lower values force harder strategic choices.
            </p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                {{-- 11-a-side --}}
                <div>
                    <label class="block font-mono text-xs font-bold text-on-surface uppercase tracking-wider mb-1">
                        11-a-side Budget
                    </label>
                    <p class="font-mono text-[11px] text-on-surface-variant/50 mb-3">Cap for full 11-player squads.</p>
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

                {{-- 5-a-side --}}
                <div>
                    <label class="block font-mono text-xs font-bold text-on-surface uppercase tracking-wider mb-1">
                        5-a-side Budget
                    </label>
                    <p class="font-mono text-[11px] text-on-surface-variant/50 mb-3">Cap for 5-a-side squads.</p>
                    <div class="flex items-center gap-3">
                        <input
                            type="number"
                            min="1"
                            max="99999"
                            wire:model="fantasyBudget5"
                            class="w-32 text-center px-4 py-2.5 rounded-xl border font-mono text-lg font-bold
                                   bg-surface-container border-outline-variant/30 text-on-surface
                                   focus:outline-none focus:border-primary-container/60
                                   transition-colors [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none">
                        <span class="font-mono text-xs text-on-surface-variant/50">coins</span>
                    </div>
                    @error('fantasyBudget5') <p class="text-red-400 text-xs font-mono mt-2">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Player price reference --}}
            @if($avgPlayerPrice > 0)
                <div class="rounded-xl bg-surface-container/50 border border-outline-variant/15 p-4 space-y-4">
                    <p class="font-mono text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/50">Current Player Price Reference</p>

                    {{-- 11-a-side reference --}}
                    <div>
                        <p class="font-mono text-[10px] font-bold uppercase tracking-wider text-primary-container/70 mb-2">11-a-side</p>
                        <div class="grid grid-cols-3 gap-3">
                            <div class="text-center">
                                <span class="block font-mono text-lg font-black text-on-surface">{{ $minSquadCost }}</span>
                                <span class="font-mono text-[9px] text-on-surface-variant/50 uppercase tracking-wider">Min<br>(11 cheapest)</span>
                            </div>
                            <div class="text-center">
                                <span class="block font-mono text-lg font-black text-primary-container">{{ $avgPlayerPrice * 11 }}</span>
                                <span class="font-mono text-[9px] text-on-surface-variant/50 uppercase tracking-wider">Avg<br>(avg × 11)</span>
                            </div>
                            <div class="text-center">
                                <span class="block font-mono text-lg font-black text-on-surface">{{ $maxSquadCost }}</span>
                                <span class="font-mono text-[9px] text-on-surface-variant/50 uppercase tracking-wider">Max<br>(11 priciest)</span>
                            </div>
                        </div>
                    </div>

                    {{-- 5-a-side reference --}}
                    <div class="pt-3 border-t border-outline-variant/10">
                        <p class="font-mono text-[10px] font-bold uppercase tracking-wider text-primary-container/70 mb-2">5-a-side</p>
                        <div class="grid grid-cols-3 gap-3">
                            <div class="text-center">
                                <span class="block font-mono text-lg font-black text-on-surface">{{ $minSquadCost5 }}</span>
                                <span class="font-mono text-[9px] text-on-surface-variant/50 uppercase tracking-wider">Min<br>(5 cheapest)</span>
                            </div>
                            <div class="text-center">
                                <span class="block font-mono text-lg font-black text-primary-container">{{ $avgPlayerPrice * 5 }}</span>
                                <span class="font-mono text-[9px] text-on-surface-variant/50 uppercase tracking-wider">Avg<br>(avg × 5)</span>
                            </div>
                            <div class="text-center">
                                <span class="block font-mono text-lg font-black text-on-surface">{{ $maxSquadCost5 }}</span>
                                <span class="font-mono text-[9px] text-on-surface-variant/50 uppercase tracking-wider">Max<br>(5 priciest)</span>
                            </div>
                        </div>
                    </div>

                    <p class="font-mono text-[10px] text-on-surface-variant/40 leading-relaxed pt-1">
                        Set each budget between its min and avg squad cost for tight strategic games.
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
