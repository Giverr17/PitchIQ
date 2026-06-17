<?php

namespace Database\Seeders;

use App\Models\TokenCost;
use Illuminate\Database\Seeder;

class TokenCostSeeder extends Seeder
{
    public function run(): void
    {
        $costs = [
            [
                'feature'     => TokenCost::PREDICTION,
                'label'       => 'Match Prediction',
                'description' => 'Tokens spent when a user submits predictions for a fixture.',
                'cost'        => 1,
            ],
            [
                'feature'     => TokenCost::SQUAD_BUILDER,
                'label'       => 'Fantasy Squad Entry',
                'description' => 'Tokens spent when a user creates their first fantasy squad for a tournament.',
                'cost'        => 5,
            ],
            [
                'feature'     => TokenCost::GAME,
                'label'       => 'Game Entry',
                'description' => 'Tokens spent when a user enters a mini-game.',
                'cost'        => 2,
            ],
        ];

        foreach ($costs as $data) {
            TokenCost::updateOrCreate(['feature' => $data['feature']], $data);
        }

        $this->command->info('✅  TokenCostSeeder complete.');
    }
}
