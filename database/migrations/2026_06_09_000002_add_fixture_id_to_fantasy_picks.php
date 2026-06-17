<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fantasy_picks', function (Blueprint $table) {
            $table->foreignId('fixture_id')
                ->nullable()
                ->after('fantasy_team_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('fantasy_picks', function (Blueprint $table) {
            $table->dropForeign(['fixture_id']);
            $table->dropColumn('fixture_id');
        });
    }
};
