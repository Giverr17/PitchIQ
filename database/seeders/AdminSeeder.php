<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */ 
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@pitchiq.com'],
            [
                'name' => 'PitchIQ Admin',
                'password' => Hash::make('password'),
                'is_admin' => true,
                'tokens' => 999,
                'faculty' => 'Sciences',
            ]
        );
    }
}
