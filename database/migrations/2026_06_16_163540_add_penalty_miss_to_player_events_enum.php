<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE player_events MODIFY COLUMN event_type ENUM(
            'goal', 'assist', 'yellow', 'red', 'own_goal',
            'penalty_saved', 'penalty_miss', 'sub_on', 'sub_off'
        ) NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE player_events MODIFY COLUMN event_type ENUM(
            'goal', 'assist', 'yellow', 'red', 'own_goal',
            'penalty_saved', 'sub_on', 'sub_off'
        ) NOT NULL");
    }
};