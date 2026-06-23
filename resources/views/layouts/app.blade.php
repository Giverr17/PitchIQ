<!DOCTYPE html>
<html class="dark" lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="description"
        content="@yield('meta_description', 'PitchIQ — The first-ever free faculty and departmental fantasy football platform built for your university. Draft players, win campus prizes.')" />
    <meta name="theme-color" content="#080C0A" />
    <title>@yield('title', 'PitchIQ') — Own Your Squad. Rule the Campus.</title>

    <!-- Favicon -->
    <link rel="icon"
        href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>⚽</text></svg>" />

    {{-- Fonts and icons are self-hosted via npm (works offline) --}}

    {{-- Hide Material Symbol ligature text before the font/CSS arrives.
         font-display:block in the @font-face only works once CSS is parsed;
         this inline rule applies the moment the HTML is received. --}}
    <style>
        /* Clamp the icon span to its real 1em footprint before the font arrives.
           Without this, the fallback system font renders "sports_soccer" as
           ~200px of invisible text that displaces adjacent labels and nav items.
           max-width:1em + overflow:hidden clips the raw text to the icon's
           actual size; opacity:0 keeps it invisible until icons-ready fires. */
        .material-symbols-outlined {
            font-family: 'Material Symbols Outlined';
            font-feature-settings: "liga";
            max-width: 1em;
            overflow: hidden;
            opacity: 0;
            line-height: 1;
        }
    </style>

    <!-- Vite Assets (CSS + JS) -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @yield('head')
</head>

<body class="text-on-surface antialiased overflow-x-hidden">

    {{-- Ambient Background Blobs --}}
    <div class="ambient-blob w-[800px] h-[800px] bg-primary-container/[0.035] top-[-200px] left-[-250px]"
        style="animation-delay: 0s;"></div>
    <div class="ambient-blob w-[500px] h-[500px] bg-secondary-container/[0.025] top-[60%] right-[-150px]"
        style="animation-delay: 5s;"></div>

    {{-- Navigation --}}
    @include('partials.nav')

    {{-- Mobile Menu Overlay --}}
    <div id="nav-overlay"
        class="fixed inset-0 bg-black/60 backdrop-blur-sm z-40 hidden opacity-0 transition-opacity duration-250 md:hidden">
    </div>

    {{-- Main Content --}}
    <main>
        @yield('content')
        {{ $slot ?? '' }}
    </main>

    {{-- Footer --}}
    @include('partials.footer')

    {{-- SortableJS is bundled via Vite (app.js) — no CDN needed --}}

    {{-- Livewire's JS engine — defines $wire, powers wire:click and our drag bridge --}}
    @livewireScripts

    {{-- Page-specific scripts --}}
    @yield('scripts')

    {{-- Ad slot — only pages that @push('ads') will show ads here --}}
    @stack('ads')
</body>

</html>