<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fantasy_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fixture_id')->constrained()->cascadeOnDelete();
            $table->string('team_name');
            $table->string('formation', 10)->default('4-3-3');
            $table->unsignedSmallInteger('total_points')->default(0);
            $table->unsignedSmallInteger('budget_remaining')->default(1000); // fantasy coins
            $table->unique(['user_id', 'fixture_id']); // one fantasy team per fixture per user
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fantasy_teams');
    }
};
