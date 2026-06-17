<?php

use Livewire\Volt\Component;
use App\Models\CampusAd;

new class extends Component {

    public ?array $ad = null;

    public function mount(): void
    {
        $picked = CampusAd::live()->inRandomOrder()->first();
        $this->ad = $picked?->toArray();
    }
} ?>

<div>
    @if($ad)
    <a href="{{ route('campus-ads.click', $ad['id']) }}"
       target="_blank"
       rel="noopener noreferrer"
       class="block rounded-xl border border-outline-variant/15 overflow-hidden group hover:border-[#00E676]/25 transition-all duration-200"
       style="background: rgba(13,17,15,0.9);">

        {{-- Sponsored label row --}}
        <div class="flex items-center justify-between px-3.5 py-1.5 border-b border-outline-variant/10"
             style="background: rgba(255,255,255,0.015);">
            <span class="font-mono text-[8px] uppercase tracking-[0.18em] text-on-surface-variant/35">Sponsored</span>
            <span class="font-mono text-[8px] text-on-surface-variant/25 flex items-center gap-0.5 uppercase tracking-widest">
                <span class="material-symbols-outlined text-[10px]">ads_click</span>Ad
            </span>
        </div>

        {{-- Banner image --}}
        <div class="relative overflow-hidden">
            <img src="{{ asset('storage/' . $ad['image_path']) }}"
                 alt="{{ $ad['business_name'] }}"
                 loading="lazy"
                 class="w-full h-20 sm:h-[88px] object-cover group-hover:scale-[1.01] transition-transform duration-300" />

            {{-- Bottom overlay: business name + visit CTA --}}
            <div class="absolute inset-0 flex items-end px-3.5 pb-2.5 pointer-events-none"
                 style="background: linear-gradient(to top, rgba(0,0,0,0.72) 0%, rgba(0,0,0,0.2) 50%, transparent 100%);">
                <div class="flex items-center justify-between w-full gap-3">
                    <span class="font-bold text-white text-sm leading-tight drop-shadow truncate">
                        {{ $ad['business_name'] }}
                    </span>
                    <span class="flex-shrink-0 flex items-center gap-1 font-mono text-[9px] text-white/70
                                 bg-black/40 border border-white/10 rounded-full px-2 py-0.5
                                 group-hover:bg-[#00E676]/20 group-hover:border-[#00E676]/30 group-hover:text-[#00E676] transition-all">
                        Visit
                        <span class="material-symbols-outlined text-[10px]">arrow_outward</span>
                    </span>
                </div>
            </div>
        </div>

    </a>
    @endif
</div>
