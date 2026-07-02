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
    {{-- Self-contained: the max-width + spacing live inside the @if, so an empty
         slot (no live ad) takes up zero vertical space on the page. --}}
    <div class="max-w-5xl mx-auto px-4 sm:px-8 pt-4">
        <a href="{{ route('campus-ads.click', $ad['id']) }}"
           target="_blank"
           rel="noopener noreferrer"
           class="block rounded-2xl border border-outline-variant/15 overflow-hidden group hover:border-[#00E676]/25 transition-all duration-200"
           style="background: rgba(13,17,15,0.9);">

            {{-- Sponsored label row (holds the business name + CTA so the image stays uncovered) --}}
            <div class="flex items-center justify-between gap-3 px-4 py-2 border-b border-outline-variant/10"
                 style="background: rgba(255,255,255,0.015);">
                <div class="flex items-center gap-2.5 min-w-0">
                    <span class="font-mono text-[8px] uppercase tracking-[0.18em] text-on-surface-variant/35 flex-shrink-0">Sponsored</span>
                    <span class="font-bold text-white text-sm truncate">{{ $ad['business_name'] }}</span>
                </div>
                <span class="flex-shrink-0 flex items-center gap-1 font-mono text-[9px] text-white/70
                             bg-black/40 border border-white/10 rounded-full px-2.5 py-1
                             group-hover:bg-[#00E676]/20 group-hover:border-[#00E676]/30 group-hover:text-[#00E676] transition-all">
                    Visit
                    <span class="material-symbols-outlined text-[11px]">arrow_outward</span>
                </span>
            </div>

            {{-- Banner image — object-contain + a taller box so the WHOLE image shows
                 (never cropped), at a comfortable size on mobile and desktop. --}}
            <div class="flex items-center justify-center overflow-hidden" style="background: rgba(0,0,0,0.25);">
                <img src="{{ asset('storage/' . $ad['image_path']) }}"
                     alt="{{ $ad['business_name'] }}"
                     loading="lazy"
                     class="w-full h-44 sm:h-56 md:h-64 object-contain group-hover:scale-[1.01] transition-transform duration-300" />
            </div>

        </a>
    </div>
    @endif
</div>
