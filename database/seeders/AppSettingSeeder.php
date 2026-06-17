<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use Illuminate\Database\Seeder;

class AppSettingSeeder extends Seeder
{
    public function run(): void
    {
        AppSetting::updateOrCreate(['key' => AppSetting::FANTASY_BUDGET], [
            'label'       => 'Fantasy Squad Budget',
            'description' => 'Total budget cap for picking a squad. Set this relative to player prices to control how strategic squad selection is. Lower = harder choices.',
            'value'       => '700',
        ]);

        $this->command->info('✅  AppSettingSeeder complete.');
    }
}
