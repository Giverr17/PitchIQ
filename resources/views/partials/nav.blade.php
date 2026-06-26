<nav id="navbar"
     class="fixed top-0 left-0 right-0 z-50 border-b border-outline-variant/10"
     style="background: rgba(8,12,10,0.7); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);">

    <div class="max-w-7xl mx-auto px-5 sm:px-8 flex items-center justify-between h-16 md:h-[70px]">

        {{-- ── Logo ── --}}
        <a href="/" class="flex items-center gap-2 flex-shrink-0 group">
            <div class="w-8 h-8 rounded-lg bg-primary-container/15 border border-primary-container/30
                        flex items-center justify-center group-hover:bg-primary-container/25 transition-colors">
                <span class="text-primary-container text-lg leading-none">⚽</span>
            </div>
            <span class="font-display font-black text-xl text-on-surface tracking-tight">
                Pitch<span class="text-gradient">IQ</span>
            </span>
        </a>

        {{-- ── Desktop Nav Links ── --}}
        <div class="hidden md:flex items-center gap-1">
            <a href="{{ route('games') }}"        class="nav-link">Games</a>
            <a href="{{ route('events') }}"       class="nav-link">Events</a>
            <a href="{{ route('leaderboard') }}"  class="nav-link">Leaderboard</a>
            <a href="{{ route('stats') }}"        class="nav-link">Stats</a>
            <a href="{{ route('how-it-works') }}" class="nav-link">How It Works</a>
            <a href="{{ route('prizes') }}"       class="nav-link">Prizes</a>
        </div>

        {{-- ── Desktop CTA ── --}}
        <div class="hidden md:flex items-center gap-3">
            @auth
                {{-- Token Balance --}}
                <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-surface-container border border-outline-variant/20 font-mono text-xs text-primary-container font-bold">
                    <span>🪙</span>
                    <span>{{ Auth::user()->tokens }} Tokens</span>
                </div>

                {{-- User Dropdown --}}
                <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                    <button @click="open = !open"
                            class="flex items-center gap-2 px-3 py-2 rounded-lg border border-outline-variant/20
                                   hover:border-primary-container/40 hover:bg-surface-container transition-all cursor-pointer">
                        <span class="w-6 h-6 rounded-full bg-primary-container/20 border border-primary-container/30
                                     flex items-center justify-center text-[10px] font-black text-primary-container">
                            {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                        </span>
                        <span class="font-mono text-xs font-semibold text-on-surface">
                            {{ \Illuminate\Support\Str::words(Auth::user()->name, 1, '') }}
                        </span>
                        <span class="material-symbols-outlined text-[14px] text-on-surface-variant transition-transform duration-150"
                              :class="open ? 'rotate-180' : ''">expand_more</span>
                    </button>

                    <div x-show="open"
                         x-transition:enter="transition ease-out duration-100"
                         x-transition:enter-start="opacity-0 -translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-75"
                         x-transition:leave-start="opacity-100 translate-y-0"
                         x-transition:leave-end="opacity-0 -translate-y-1"
                         class="absolute right-0 top-full mt-2 w-52 rounded-xl border border-outline-variant/20 shadow-2xl overflow-hidden z-50"
                         style="background: rgba(13,17,15,0.97); backdrop-filter: blur(20px);">
                        <div class="p-1.5 flex flex-col gap-0.5">
                            @foreach([
                                ['route' => 'dashboard',        'icon' => 'dashboard',     'label' => 'Dashboard'],
                                ['route' => 'squad.builder',    'icon' => 'sports_soccer', 'label' => 'Squad Builder'],
                                ['route' => 'mini-leagues',     'icon' => 'groups',        'label' => 'Mini Leagues'],
                                ['route' => 'predictions.index','icon' => 'query_stats',   'label' => 'Predictions'],
                                ['route' => 'my-stats',         'icon' => 'insights',      'label' => 'My Stats'],
                                ['route' => 'profile.edit',     'icon' => 'manage_accounts','label' => 'Edit Profile'],
                            ] as $item)
                            <a href="{{ route($item['route']) }}"
                               class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-xs font-mono font-semibold
                                      transition-all
                                      {{ request()->routeIs($item['route'])
                                         ? 'text-primary-container bg-primary-container/8'
                                         : 'text-on-surface-variant hover:text-on-surface hover:bg-surface-container' }}">
                                <span class="material-symbols-outlined text-[16px]">{{ $item['icon'] }}</span>
                                {{ $item['label'] }}
                            </a>
                            @endforeach
                            <div class="h-px bg-outline-variant/20 my-1 mx-2"></div>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit"
                                        class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-xs font-mono
                                               font-semibold text-error/60 hover:text-error hover:bg-error-container/10
                                               transition-all cursor-pointer">
                                    <span class="material-symbols-outlined text-[16px]">logout</span>
                                    Logout
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @else
                <a href="{{ route('login') }}"
                   class="text-on-surface-variant hover:text-on-surface font-mono text-xs font-semibold
                          tracking-wider uppercase px-4 py-2 rounded-lg transition-colors hover:bg-surface-container">
                    Login
                </a>
                <a href="{{ route('register') }}"
                   class="bg-primary-container text-background font-mono text-xs font-bold tracking-wider
                          uppercase px-5 py-2.5 rounded-lg hover:bg-primary-fixed transition-colors
                          glow-green animate-pulse-glow">
                    Play Now
                </a>
            @endauth
        </div>

        {{-- ── Mobile Hamburger ── --}}
        <button id="nav-toggle"
                aria-label="Toggle mobile menu"
                aria-expanded="false"
                class="md:hidden flex flex-col justify-center items-center w-10 h-10 rounded-lg
                       border border-outline-variant/40 hover:border-primary-container/40
                       hover:bg-surface-container transition-all gap-[5px] flex-shrink-0">
            <span class="w-5 h-[1.5px] bg-on-surface-variant rounded-full transition-all"></span>
            <span class="w-5 h-[1.5px] bg-on-surface-variant rounded-full transition-all"></span>
            <span class="w-3.5 h-[1.5px] bg-on-surface-variant rounded-full transition-all self-start ml-[5px]"></span>
        </button>
    </div>
</nav>

{{-- ── Mobile Drawer ──────────────────────────────────────────────────────────
     Placed OUTSIDE <nav> to avoid backdrop-filter creating a new containing
     block, which would cap `fixed` children to the navbar height (~64px).
─────────────────────────────────────────────────────────────────────────── --}}
<div id="mobile-menu"
     class="fixed top-0 left-0 h-[100dvh] w-72 bg-surface-container-lowest border-r border-outline-variant/20
            -translate-x-full transition-transform duration-300 ease-in-out z-50 flex flex-col md:hidden">

    {{-- Drawer Header --}}
    <div class="flex items-center justify-between px-6 h-16 border-b border-outline-variant/20 flex-shrink-0">
        <span class="font-display font-black text-xl text-on-surface">
            Pitch<span class="text-gradient">IQ</span>
        </span>
        <button onclick="window.closeMobileMenu()"
                class="w-8 h-8 rounded-lg border border-outline-variant/30 flex items-center justify-center
                       hover:border-error/50 hover:bg-error-container/10 transition-all">
            <span class="text-on-surface-variant text-lg leading-none">&times;</span>
        </button>
    </div>

    {{-- User info strip (auth only) --}}
    @auth
    <div class="flex items-center gap-3 px-5 py-4 border-b border-outline-variant/20 flex-shrink-0"
         style="background: rgba(0,230,118,0.03);">
        <div class="w-10 h-10 rounded-full bg-primary-container/20 border border-primary-container/30
                    flex items-center justify-center font-black text-sm text-primary-container flex-shrink-0">
            {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
        </div>
        <div class="min-w-0 flex-1">
            <p class="font-bold text-sm text-on-surface truncate">{{ Auth::user()->name }}</p>
            <p class="font-mono text-[10px] text-on-surface-variant/60 mt-0.5">🪙 {{ Auth::user()->tokens }} tokens</p>
        </div>
    </div>
    @endauth

    {{-- Drawer Links --}}
    <nav class="flex-1 overflow-y-auto px-4 py-6 flex flex-col gap-1">

        {{-- Public pages --}}
        <a href="{{ route('games') }}"
           class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-sm">
            <span class="material-symbols-outlined text-[18px]">sports_soccer</span>
            Games
        </a>
        <a href="{{ route('events') }}"
           class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-sm">
            <span class="material-symbols-outlined text-[18px]">event</span>
            Events
        </a>
        <a href="{{ route('leaderboard') }}"
           class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-sm">
            <span class="material-symbols-outlined text-[18px]">leaderboard</span>
            Leaderboard
        </a>
        <a href="{{ route('stats') }}"
           class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-sm">
            <span class="material-symbols-outlined text-[18px]">query_stats</span>
            Stats
        </a>
        <a href="{{ route('how-it-works') }}"
           class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-sm">
            <span class="material-symbols-outlined text-[18px]">help_outline</span>
            How It Works
        </a>
        <a href="{{ route('features') }}"
           class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-sm">
            <span class="material-symbols-outlined text-[18px]">grid_view</span>
            Features
        </a>
        <a href="{{ route('prizes') }}"
           class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-sm">
            <span class="material-symbols-outlined text-[18px]">emoji_events</span>
            Prizes
        </a>

        {{-- Auth-only pages --}}
        @auth
        <div class="h-px bg-outline-variant/20 my-2 mx-2"></div>
        <a href="{{ route('dashboard') }}"
           class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-sm">
            <span class="material-symbols-outlined text-[18px]">dashboard</span>
            Dashboard
        </a>
        <a href="{{ route('squad.builder') }}"
           class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-sm">
            <span class="material-symbols-outlined text-[18px]">sports_soccer</span>
            Squad Builder
        </a>
        <a href="{{ route('mini-leagues') }}"
           class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-sm">
            <span class="material-symbols-outlined text-[18px]">groups</span>
            Mini Leagues
        </a>
        <a href="{{ route('predictions.index') }}"
           class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-sm">
            <span class="material-symbols-outlined text-[18px]">query_stats</span>
            Predictions
        </a>
        <a href="{{ route('my-stats') }}"
           class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-sm">
            <span class="material-symbols-outlined text-[18px]">insights</span>
            My Stats
        </a>
        <a href="{{ route('profile.edit') }}"
           class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-sm">
            <span class="material-symbols-outlined text-[18px]">manage_accounts</span>
            Edit Profile
        </a>
        @endauth
    </nav>

    {{-- Drawer Footer --}}
    <div class="px-4 pb-6 flex flex-col gap-3 border-t border-outline-variant/20 pt-4 flex-shrink-0">
        @auth
            <form method="POST" action="{{ route('logout') }}" class="w-full">
                @csrf
                <button type="submit"
                        class="w-full text-center py-3 rounded-xl bg-error-container/10 text-error font-mono text-xs
                               font-bold tracking-wider uppercase hover:bg-error-container/20 transition-colors cursor-pointer">
                    Logout
                </button>
            </form>
        @else
            <a href="{{ route('login') }}"
               class="w-full text-center py-3 rounded-xl border border-outline-variant/50 text-on-surface-variant
                      font-mono text-xs font-semibold tracking-wider uppercase hover:border-outline
                      hover:text-on-surface transition-colors">
                Login
            </a>
            <a href="{{ route('register') }}"
               class="w-full text-center py-3 rounded-xl bg-primary-container text-background font-mono text-xs
                      font-bold tracking-wider uppercase hover:bg-primary-fixed transition-colors glow-green">
                Play Now — It's Free
            </a>
        @endauth
    </div>
</div>

{{-- Spacer to push content below fixed nav --}}
<div class="h-16 md:h-[70px]"></div>
