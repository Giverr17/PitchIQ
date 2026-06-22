<?php

use App\Enums\FixtureStatus;
use App\Enums\TournamentStatus;
use App\Models\Fixture;
use App\Models\FantasyTeam;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// ─── Public pages ──────────────────────────────────────────────────────────────
Route::get('/', function () {
    $welcomeFixtures = Fixture::with(['homeTeam', 'awayTeam', 'tournament'])
        ->whereIn('status', [FixtureStatus::Live, FixtureStatus::Scheduled])
        ->orderByRaw("CASE WHEN status = 'live' THEN 0 ELSE 1 END")
        ->orderBy('date')
        ->limit(6)
        ->get();

    // Real platform stats (no fabricated numbers)
    $stats = [
        'players'     => User::count(),
        'faculties'   => Team::whereNotNull('faculty')->distinct()->count('faculty'),
        'gamesPlayed' => Fixture::where('status', FixtureStatus::Completed)->count(),
        'tournaments' => Tournament::count(),
    ];

    // Top fantasy managers by total points (across all tournaments)
    $topManagers = FantasyTeam::join('users', 'fantasy_teams.user_id', '=', 'users.id')
        ->groupBy('fantasy_teams.user_id', 'users.name', 'users.faculty')
        ->select(
            'users.name as manager',
            'users.faculty',
            DB::raw('SUM(fantasy_teams.total_points) as pts'),
            DB::raw('MAX(fantasy_teams.team_name) as squad'),
        )
        ->orderByDesc('pts')
        ->limit(5)
        ->get();

    // Real upcoming/active tournaments for the events section
    $welcomeEvents = Tournament::withCount('teams')
        ->whereIn('status', [TournamentStatus::Upcoming, TournamentStatus::Active])
        ->orderByRaw("FIELD(status, 'active', 'upcoming')")
        ->orderBy('start_date')
        ->limit(3)
        ->get();

    return view('welcome', compact('welcomeFixtures', 'stats', 'topManagers', 'welcomeEvents'));
})->name('home');
Route::get('/games', function () {
    $liveFixtures = Fixture::with(['homeTeam', 'awayTeam', 'tournament'])
        ->where('status', 'live')
        ->orderBy('date')
        ->get();
    $upcomingFixtures = Fixture::with(['homeTeam', 'awayTeam', 'tournament'])
        ->whereIn('status', ['scheduled', 'postponed'])
        ->orderBy('date')
        ->limit(9)
        ->get();
    $completedFixtures = Fixture::with(['homeTeam', 'awayTeam', 'tournament'])
        ->where('status', 'completed')
        ->latest('date')
        ->limit(8)
        ->get();
    return view('games', compact('liveFixtures', 'upcomingFixtures', 'completedFixtures'));
})->name('games');

Route::get('/events', function () {
    $tournaments = Tournament::withCount(['teams', 'fantasyTeams'])
        ->orderByRaw("CASE WHEN status = 'active' THEN 0 WHEN status = 'upcoming' THEN 1 ELSE 2 END")
        ->orderBy('start_date')
        ->get();
    $featured = $tournaments->first();
    $featuredNextFixture = $featured
        ? $featured->fixtures()->whereIn('status', ['scheduled', 'live'])->orderBy('date')->first()
        : null;
    $eventCards = $featured ? $tournaments->slice(1) : collect();
    return view('events', compact('featured', 'featuredNextFixture', 'eventCards'));
})->name('events');
Volt::route('/leaderboard', 'leaderboard')->name('leaderboard');
Route::get('/how-it-works', fn() => view('how-it-works'))->name('how-it-works');
Route::get('/features', fn() => view('features'))->name('features');
Route::get('/prizes', fn() => view('prizes'))->name('prizes');

// ─── Guest auth pages (Livewire Volt) ─────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Volt::route('/login', 'auth.login')->name('login');
    Volt::route('/register', 'auth.register')->name('register');
});
Route::get('/campus-ads/{ad}/click', function (\App\Models\CampusAd $ad) {
    $ad->increment('clicks');
    return $ad->link_url
        ? redirect()->away($ad->link_url)
        : back();
})->name('campus-ads.click');

// ─── Authenticated user area ───────────────────────────────────────────────────
Route::middleware('auth')->group(function () {
    Volt::route('/dashboard', 'dashboard')->name('dashboard');
    // Squad builder (static blade page)
    Volt::route('/squad/builder', 'squad.builder')->name('squad.builder');
    Volt::route('/mini-leagues', 'mini-league')->name('mini-leagues');
    // Predictions page
    Volt::route('/predictions', 'predictions.index')->name('predictions.index');

    // Logout
    Route::post('/logout', function (Illuminate\Http\Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('home');
    })->name('logout');
});


// ─── Admin area (auth protected, Volt full-page routes) ───────────────────────
Volt::route('/admin', 'admin.dashboard')->middleware(['auth', 'admin'])->name('admin.dashboard');
Volt::route('/admin/tournaments', 'admin.tournaments.index')->middleware(['auth', 'admin'])->name('admin.tournaments');
Volt::route('/admin/teams', 'admin.teams.index')->middleware(['auth', 'admin'])->name('admin.teams');
Volt::route('/admin/players', 'admin.players.index')->middleware(['auth', 'admin'])->name('admin.players');
Volt::route('/admin/fixtures', 'admin.fixtures.index')->middleware(['auth', 'admin'])->name('admin.fixtures');
Volt::route('/admin/results', 'admin.results.index')->middleware(['auth', 'admin'])->name('admin.results');
Volt::route('/admin/predictions', 'admin.predictions.index')->middleware(['auth', 'admin'])->name('admin.predictions');
Volt::route('/admin/token-costs', 'admin.token-costs.index')->middleware(['auth', 'admin'])->name('admin.token-costs');
Volt::route('/admin/settings', 'admin.settings.index')->middleware(['auth', 'admin'])->name('admin.settings');
Volt::route('/admin/campus-ads', 'admin.campus-ads.index')->middleware(['auth', 'admin'])->name('admin.campus-ads');
Volt::route('/admin/payouts', 'admin.payouts.index')->middleware(['auth', 'admin'])->name('admin.payouts');