<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: drop the FK + composite unique index (backed by user_id as left-prefix).
        // Cannot drop and re-add the same FK name in one statement — MySQL name-clash.
        DB::statement("
            ALTER TABLE fantasy_teams
                DROP FOREIGN KEY fantasy_teams_user_id_foreign,
                DROP INDEX fantasy_teams_user_id_tournament_id_unique
        ");

        // Step 2: add a standalone user_id index, restore the FK, add fixture_id column.
        DB::statement("
            ALTER TABLE fantasy_teams
                ADD INDEX fantasy_teams_user_id_index (user_id),
                ADD CONSTRAINT fantasy_teams_user_id_foreign
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                ADD COLUMN fixture_id BIGINT UNSIGNED NULL AFTER tournament_id
        ");

        // Step 3: add fixture FK and new unique constraint.
        DB::statement("
            ALTER TABLE fantasy_teams
                ADD CONSTRAINT fantasy_teams_fixture_id_foreign
                    FOREIGN KEY (fixture_id) REFERENCES fixtures(id) ON DELETE CASCADE,
                ADD UNIQUE INDEX fantasy_teams_user_id_fixture_id_unique (user_id, fixture_id)
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE fantasy_teams
                DROP FOREIGN KEY fantasy_teams_user_id_foreign,
                DROP FOREIGN KEY fantasy_teams_fixture_id_foreign,
                DROP INDEX fantasy_teams_user_id_fixture_id_unique,
                DROP COLUMN fixture_id,
                DROP INDEX fantasy_teams_user_id_index,
                ADD UNIQUE INDEX fantasy_teams_user_id_tournament_id_unique (user_id, tournament_id),
                ADD CONSTRAINT fantasy_teams_user_id_foreign
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ");
    }
};
