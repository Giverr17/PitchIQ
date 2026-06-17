<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * This migration was partially applied manually via MySQL CLI.
     * The up() method reflects the full intended state; the columns and
     * indexes were fixed directly in MySQL when the Blueprint approach hit
     * MySQL's "index needed in FK constraint" restriction.
     *
     * The down() method handles rollback cleanly.
     */
    public function up(): void
    {
        // Add 'type' column if it doesn't exist yet
        if (!Schema::hasColumn('predictions', 'type')) {
            Schema::table('predictions', function (Blueprint $table) {
                $table->enum('type', ['result', 'exact_score', 'first_goalscorer', 'clean_sheet', 'carded_player'])
                      ->default('result')
                      ->after('fixture_id');
            });
        }

        // Add predicted_team_id column if it doesn't exist yet
        if (!Schema::hasColumn('predictions', 'predicted_team_id')) {
            Schema::table('predictions', function (Blueprint $table) {
                $table->unsignedBigInteger('predicted_team_id')->nullable()->after('predicted_scorer_id');
                $table->foreign('predicted_team_id')->references('id')->on('teams')->nullOnDelete();
            });
        }

        // Drop the old (user_id, fixture_id) unique index and replace with (user_id, fixture_id, type)
        // MySQL requires plain indexes on user_id and fixture_id before we can drop the compound unique.
        $indexes = collect(Schema::getIndexes('predictions'))->pluck('name')->toArray();

        if (in_array('predictions_user_id_fixture_id_unique', $indexes)) {
            DB::statement('ALTER TABLE predictions ADD INDEX IF NOT EXISTS predictions_user_id_index (user_id)');
            DB::statement('ALTER TABLE predictions ADD INDEX IF NOT EXISTS predictions_fixture_id_index (fixture_id)');
            DB::statement('ALTER TABLE predictions DROP INDEX predictions_user_id_fixture_id_unique');
        }

        if (!in_array('predictions_user_fixture_type_unique', $indexes)) {
            DB::statement('ALTER TABLE predictions ADD UNIQUE predictions_user_fixture_type_unique (user_id, fixture_id, type)');
        }
    }

    public function down(): void
    {
        $indexes = collect(Schema::getIndexes('predictions'))->pluck('name')->toArray();

        // Remove the new unique and restore the old one
        if (in_array('predictions_user_fixture_type_unique', $indexes)) {
            DB::statement('ALTER TABLE predictions DROP INDEX predictions_user_fixture_type_unique');
        }
        if (!in_array('predictions_user_id_fixture_id_unique', $indexes)) {
            DB::statement('ALTER TABLE predictions ADD UNIQUE predictions_user_id_fixture_id_unique (user_id, fixture_id)');
        }

        // Drop added columns
        Schema::table('predictions', function (Blueprint $table) {
            if (Schema::hasColumn('predictions', 'predicted_team_id')) {
                $table->dropForeign(['predicted_team_id']);
                $table->dropColumn('predicted_team_id');
            }
            if (Schema::hasColumn('predictions', 'type')) {
                $table->dropColumn('type');
            }
        });
    }
};
