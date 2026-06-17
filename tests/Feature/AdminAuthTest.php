<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirects guests to login', function () {
    $this->get('/admin')
        ->assertRedirect('/login');
});

it('blocks non-admin users from accessing admin routes', function () {
    $user = User::factory()->create([
        'is_admin' => false,
    ]);

    $this->actingAs($user)
        ->get('/admin')
        ->assertStatus(403);
});

it('allows admin users to access admin routes', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    $this->actingAs($admin)
        ->get('/admin')
        ->assertStatus(200);
});
