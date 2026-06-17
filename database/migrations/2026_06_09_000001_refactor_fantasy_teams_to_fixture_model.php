<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Wipe picks first — they reference fantasy_teams rows that are incompatible
        DB::statement('DELETE FROM fantasy_picks');
        DB::statement('DELETE FROM fantasy_teams');

        // Drop the per-matchday unique and the matchday column
        DB::statement("
            ALTER TABLE fantasy_teams
                DROP INDEX fantasy_teams_user_id_tournament_id_matchday_unique,
                DROP COLUMN matchday
        ");

        // Make fixture_id non-nullable and add the per-fixture unique constraint
        DB::statement("
            ALTER TABLE fantasy_teams
                MODIFY COLUMN fixture_id BIGINT UNSIGNED NOT NULL,
                ADD UNIQUE INDEX fantasy_teams_user_id_fixture_id_unique (user_id, fixture_id)
        ");
    }

    public function down(): void
    {
        DB::statement('DELETE FROM fantasy_picks');
        DB::statement('DELETE FROM fantasy_teams');

        DB::statement("
            ALTER TABLE fantasy_teams
                DROP INDEX fantasy_teams_user_id_fixture_id_unique,
                MODIFY COLUMN fixture_id BIGINT UNSIGNED NULL,
                ADD COLUMN matchday TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER fixture_id,
                ADD UNIQUE INDEX fantasy_teams_user_id_tournament_id_matchday_unique
                    (user_id, tournament_id, matchday)
        ");
    }
};
