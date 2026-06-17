<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Existing rows are per-fixture (old model) and incompatible with the new
        // per-matchday unique constraint. Wipe them before reshaping the table.
        DB::statement('DELETE FROM fantasy_picks');
        DB::statement('DELETE FROM fantasy_teams');

        // Drop old per-fixture unique, add matchday column, add per-matchday unique
        DB::statement("
            ALTER TABLE fantasy_teams
                DROP INDEX fantasy_teams_user_id_fixture_id_unique,
                ADD COLUMN matchday TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER fixture_id,
                ADD UNIQUE INDEX fantasy_teams_user_id_tournament_id_matchday_unique
                    (user_id, tournament_id, matchday)
        ");
    }

    public function down(): void
    {
        DB::statement('DELETE FROM fantasy_picks');
        DB::statement('DELETE FROM fantasy_teams');

        DB::statement("
            ALTER TABLE fantasy_teams
                DROP INDEX fantasy_teams_user_id_tournament_id_matchday_unique,
                DROP COLUMN matchday,
                ADD UNIQUE INDEX fantasy_teams_user_id_fixture_id_unique (user_id, fixture_id)
        ");
    }
};
