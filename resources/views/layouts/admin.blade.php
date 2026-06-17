<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="description" content="PitchIQ Admin — Fantasy Football Management Portal" />
    <meta name="theme-color" content="#080C0A" />
    <title>@yield('title', 'Admin') — PitchIQ</title>

    <!-- Favicon -->
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🛡️</text></svg>" />

    {{-- Fonts and icons are self-hosted via npm (works offline) --}}

    {{-- Hide Material Symbol ligature text before the font/CSS arrives. --}}
    <style>
        .material-symbols-outlined {
            font-family: 'Material Symbols Outlined';
            font-feature-settings: "liga";
            max-width: 1em;
            overflow: hidden;
            opacity: 0;
            line-height: 1;
        }
    </style>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>

@php
    $pageTitle = match(request()->route()?->getName()) {
        'admin.dashboard'   => 'Dashboard',
        'admin.tournaments' => 'Tournaments',
        'admin.teams'       => 'Teams',
        'admin.players'     => 'Players',
        'admin.fixtures'    => 'Fixtures',
        'admin.results'     => 'Results',
        'admin.predictions' => 'Predictions',
        'admin.token-costs' => 'Token Costs',
        'admin.settings'    => 'Settings',
        'admin.campus-ads'  => 'Campus Ads',
        default             => 'Admin Panel',
    };

    $navLinks = [
        ['route' => 'admin.dashboard',   'path' => '/admin',              'icon' => 'dashboard',      'label' => 'Dashboard'],
        ['route' => 'admin.tournaments', 'path' => '/admin/tournaments',  'icon' => 'emoji_events',   'label' => 'Tournaments'],
        ['route' => 'admin.teams',       'path' => '/admin/teams',        'icon' => 'groups',         'label' => 'Teams'],
        ['route' => 'admin.players',     'path' => '/admin/players',      'icon' => 'sports_soccer',  'label' => 'Players'],
        ['route' => 'admin.fixtures',    'path' => '/admin/fixtures',     'icon' => 'calendar_month', 'label' => 'Fixtures'],
        ['route' => 'admin.results',      'path' => '/admin/results',      'icon' => 'sports_score',   'label' => 'Results'],
        ['route' => 'admin.predictions', 'path' => '/admin/predictions',  'icon' => 'fact_check',     'label' => 'Predictions'],
        ['route' => 'admin.token-costs', 'path' => '/admin/token-costs',  'icon' => 'token',          'label' => 'Token Costs'],
        ['route' => 'admin.settings',    'path' => '/admin/settings',     'icon' => 'tune',           'label' => 'Settings'],
        ['route' => 'admin.campus-ads', 'path' => '/admin/campus-ads', 'icon' => 'storefront', 'label' => 'Campus Ads'],
        ['route' => 'admin.payouts', 'path' => '/admin/payouts', 'icon' => 'redeem', 'label' => 'Payouts'],
    ];
@endphp

<body class="bg-[#080C0A] text-on-surface antialiased overflow-x-hidden" style="font-family: 'Plus Jakarta Sans', sans-serif;" x-data="{ sidebarOpen: false }">

    {{-- Ambient Background Blobs --}}
    <div class="ambient-blob w-[600px] h-[600px] bg-primary-container/[0.025] top-[-100px] left-[20%] pointer-events-none"
         style="animation-delay: 0s;"></div>
    <div class="ambient-blob w-[400px] h-[400px] bg-secondary-container/[0.015] bottom-[-100px] right-[10%] pointer-events-none"
         style="animation-delay: 4s;"></div>

<div class="flex min-h-screen">

    {{-- ===================== --}}
    {{-- SIDEBAR (Desktop)     --}}
    {{-- ===================== --}}
    <aside class="hidden md:flex w-64 bg-[#0d110f] flex-col fixed inset-y-0 left-0 z-40 border-r border-outline-variant/15">

        {{-- Logo --}}
        <div class="h-16 flex items-center gap-3 px-6 border-b border-outline-variant/15 flex-shrink-0">
            <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgba(0,230,118,0.15); border: 1px solid rgba(0,230,118,0.3);">
                <span class="text-sm">🛡️</span>
            </div>
            <div class="leading-none">
                <span class="font-black text-xl text-white tracking-tight" style="font-family: 'Montserrat', sans-serif;">
                    Pitch<span class="text-primary-container">IQ</span>
                </span>
                <span class="block text-[9px] font-mono text-on-surface-variant/40 uppercase tracking-widest mt-0.5">Admin Panel</span>
            </div>
        </div>

        {{-- Nav --}}
        <nav class="flex-1 px-3 py-5 space-y-1.5 overflow-y-auto">
            @foreach($navLinks as $link)
                @php $active = request()->routeIs($link['route']); @endphp
                <a href="{{ route($link['route']) }}"
                   class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium font-mono uppercase tracking-wider text-[11px] transition-all duration-200 group
                          {{ $active ? 'text-background font-bold shadow-lg shadow-primary-container/10' : 'text-on-surface-variant hover:text-primary-container hover:bg-primary-container/5' }}"
                   @if($active) style="background: linear-gradient(135deg, #00E676 0%, #00b359 100%); border: 1px solid rgba(117,255,158,0.2);" @endif>
                    <span class="material-symbols-outlined text-[18px] {{ $active ? 'text-background' : 'text-on-surface-variant/70 group-hover:text-primary-container' }}">{{ $link['icon'] }}</span>
                    {{ $link['label'] }}
                </a>
            @endforeach
        </nav>

        {{-- Sidebar footer --}}
        <div class="p-4 border-t border-outline-variant/15 space-y-3 flex-shrink-0">
            @auth
                <div class="flex items-center gap-2.5 px-3 py-2 rounded-xl bg-surface-container/20 border border-outline-variant/15">
                    <div class="w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0 font-black text-xs text-background" style="background: linear-gradient(135deg, #00E676 0%, #00b359 100%);">
                        {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                    </div>
                    <span class="font-mono text-xs text-on-surface-variant truncate">{{ Auth::user()->name }}</span>
                </div>
            @endauth
            <a href="{{ route('dashboard') }}"
               class="flex items-center justify-center gap-2 w-full py-2.5 rounded-xl border border-outline-variant/20 text-xs font-mono font-bold text-on-surface-variant hover:text-primary-container hover:border-primary-container/40 hover:bg-primary-container/5 transition-all uppercase tracking-wider">
                <span class="material-symbols-outlined text-[14px]">arrow_back</span>
                Player Lobby
            </a>
        </div>
    </aside>

    {{-- ===================== --}}
    {{-- MOBILE OVERLAY        --}}
    {{-- ===================== --}}
    <div x-show="sidebarOpen"
         x-transition:enter="transition-opacity ease-linear duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-linear duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="sidebarOpen = false"
         class="fixed inset-0 z-40 bg-black/70 md:hidden"
         style="display:none;"></div>

    <aside x-show="sidebarOpen"
           x-transition:enter="transition ease-in-out duration-250 transform"
           x-transition:enter-start="-translate-x-full"
           x-transition:enter-end="translate-x-0"
           x-transition:leave="transition ease-in-out duration-250 transform"
           x-transition:leave-start="translate-x-0"
           x-transition:leave-end="-translate-x-full"
           class="fixed top-0 left-0 h-[100dvh] z-50 w-64 bg-[#0d110f] flex flex-col border-r border-outline-variant/15 md:hidden"
           style="display:none;">

        {{-- Header --}}
        <div class="h-16 flex items-center justify-between px-6 border-b border-outline-variant/15 flex-shrink-0">
            <div class="leading-none">
                <span class="font-black text-xl text-white" style="font-family: 'Montserrat', sans-serif;">
                    Pitch<span class="text-primary-container">IQ</span>
                </span>
                <span class="block text-[9px] font-mono text-on-surface-variant/40 uppercase tracking-widest mt-0.5">Admin Panel</span>
            </div>
            <button @click="sidebarOpen = false"
                    class="w-8 h-8 rounded-lg border border-outline-variant/30 flex items-center justify-center
                           hover:border-error/50 hover:bg-error-container/10 transition-all">
                <span class="material-symbols-outlined text-[20px] text-on-surface-variant">close</span>
            </button>
        </div>

        {{-- User info strip --}}
        @auth
        <div class="flex items-center gap-3 px-5 py-4 border-b border-outline-variant/15 flex-shrink-0"
             style="background: rgba(0,230,118,0.03);">
            <div class="w-9 h-9 rounded-full flex items-center justify-center font-black text-sm text-background flex-shrink-0"
                 style="background: linear-gradient(135deg, #00E676 0%, #00b359 100%);">
                {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
            </div>
            <div class="min-w-0 flex-1">
                <p class="font-bold text-sm text-on-surface truncate">{{ Auth::user()->name }}</p>
                <p class="font-mono text-[10px] text-on-surface-variant/40 uppercase tracking-widest">Admin</p>
            </div>
        </div>
        @endauth

        {{-- Nav links --}}
        <nav class="flex-1 px-3 py-5 space-y-1.5 overflow-y-auto">
            @foreach($navLinks as $link)
                @php $active = request()->routeIs($link['route']); @endphp
                <a href="{{ route($link['route']) }}" @click="sidebarOpen = false"
                   class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium font-mono uppercase tracking-wider text-[11px] transition-all duration-200 group
                          {{ $active ? 'text-background font-bold shadow-lg shadow-primary-container/10' : 'text-on-surface-variant hover:text-primary-container hover:bg-primary-container/5' }}"
                   @if($active) style="background: linear-gradient(135deg, #00E676 0%, #00b359 100%); border: 1px solid rgba(117,255,158,0.2);" @endif>
                    <span class="material-symbols-outlined text-[18px]">{{ $link['icon'] }}</span>
                    {{ $link['label'] }}
                </a>
            @endforeach
        </nav>

        {{-- Footer: back to player lobby --}}
        <div class="p-4 border-t border-outline-variant/15 flex-shrink-0">
            <a href="{{ route('dashboard') }}" @click="sidebarOpen = false"
               class="flex items-center justify-center gap-2 w-full py-2.5 rounded-xl border border-outline-variant/20
                      text-xs font-mono font-bold text-on-surface-variant uppercase tracking-wider
                      hover:text-primary-container hover:border-primary-container/40 hover:bg-primary-container/5 transition-all">
                <span class="material-symbols-outlined text-[14px]">arrow_back</span>
                Player Lobby
            </a>
        </div>
    </aside>

    {{-- ===================== --}}
    {{-- MAIN AREA             --}}
    {{-- ===================== --}}
    <div class="flex-1 min-w-0 flex flex-col min-h-screen md:ml-64">

        {{-- Top bar --}}
        <header class="h-16 bg-[#0d110f]/80 border-b border-outline-variant/15 backdrop-blur-md sticky top-0 z-30 flex items-center justify-between px-5 sm:px-8">
            <div class="flex items-center gap-4">
                <button @click="sidebarOpen = true" class="md:hidden text-on-surface-variant hover:text-white">
                    <span class="material-symbols-outlined">menu</span>
                </button>
                <button type="button" onclick="window.history.back()" title="Back"
                        class="w-8 h-8 rounded-lg border border-outline-variant/20 flex items-center justify-center text-on-surface-variant hover:text-primary-container hover:border-primary-container/40 transition-all cursor-pointer">
                    <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                </button>
                <div>
                    <h1 class="font-display font-black text-base text-on-surface leading-none tracking-tight uppercase">{{ $pageTitle }}</h1>
                    <p class="text-primary-container text-[9px] font-mono mt-0.5 uppercase tracking-widest">PitchIQ Admin</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <span class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[9px] font-mono font-bold uppercase tracking-widest"
                      style="background:rgba(0,230,118,0.1); color:#00E676; border:1px solid rgba(0,230,118,0.25);">
                    <span class="w-1.5 h-1.5 rounded-full animate-pulse" style="background:#00E676;"></span>
                    Live
                </span>

                @auth
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                            class="flex items-center gap-2 px-3 py-1.5 rounded-xl border border-outline-variant/20 text-xs font-mono text-on-surface-variant hover:text-error hover:border-error/40 hover:bg-error/5 transition-all cursor-pointer">
                        <span class="material-symbols-outlined text-[14px]">logout</span>
                        <span class="hidden sm:inline">Logout</span>
                    </button>
                </form>
                @endauth
            </div>
        </header>

        {{-- Page content --}}
        <main class="flex-1 p-5 sm:p-8 bg-[#080C0A]">
            {{ $slot }}
        </main>

        <footer class="border-t border-outline-variant/10 bg-[#0d110f] px-8 py-3.5 flex items-center justify-between">
            <span class="font-mono text-[10px] text-on-surface-variant/40">PitchIQ Admin &copy; {{ date('Y') }}</span>
        </footer>
    </div>
</div>

@livewireScripts
</body>
</html>
