<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fantasy_teams', function (Blueprint $table) {
            $table->string('formation', 10)->default('4-3-3')->after('team_name');
        });
    }

    public function down(): void
    {
        Schema::table('fantasy_teams', function (Blueprint $table) {
            $table->dropColumn('formation');
        });
    }
};
