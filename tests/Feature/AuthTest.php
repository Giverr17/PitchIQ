<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

it('renders register component', function () {
    $this->get('/register')->assertSeeLivewire('auth.register');
});

it('can register a new user', function () {
    Volt::test('auth.register')
        ->set('name', 'Jane Doe')
        ->set('email', 'jane@example.com')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->call('register')
        ->assertRedirect(route('dashboard'));

    $this->assertDatabaseHas('users', [
        'email' => 'jane@example.com',
        'name' => 'Jane Doe',
        'tokens' => 120,
    ]);
});

it('renders login component', function () {
    $this->get('/login')->assertSeeLivewire('auth.login');
});

it('can login an existing user', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => Hash::make('password123'),
        'is_admin' => false,
    ]);

    Volt::test('auth.login')
        ->set('email', 'test@example.com')
        ->set('password', 'password123')
        ->call('login')
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

it('redirects admin users to admin dashboard', function () {
    $admin = User::factory()->create([
        'email' => 'admin@example.com',
        'password' => Hash::make('password123'),
        'is_admin' => true,
    ]);

    Volt::test('auth.login')
        ->set('email', 'admin@example.com')
        ->set('password', 'password123')
        ->call('login')
        ->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticatedAs($admin);
});
