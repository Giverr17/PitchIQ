<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use Illuminate\Database\Seeder;

class AppSettingSeeder extends Seeder
{
    public function run(): void
    {
        AppSetting::updateOrCreate(['key' => AppSetting::FANTASY_BUDGET], [
            'label'       => 'Fantasy Squad Budget (11-a-side)',
            'description' => 'Total budget cap for picking an 11-a-side squad. Set this relative to player prices to control how strategic squad selection is. Lower = harder choices.',
            'value'       => '700',
        ]);

        AppSetting::updateOrCreate(['key' => AppSetting::FANTASY_BUDGET_5], [
            'label'       => 'Fantasy Squad Budget (5-a-side)',
            'description' => 'Total budget cap for picking a 5-a-side squad.',
            'value'       => '320',
        ]);

        $this->command->info('✅  AppSettingSeeder complete.');
    }
}
