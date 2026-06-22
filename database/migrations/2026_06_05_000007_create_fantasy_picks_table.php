<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fantasy_picks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fantasy_team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fixture_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('matchday');
            $table->boolean('is_captain')->default(false);
            $table->boolean('is_vice_captain')->default(false);
            $table->smallInteger('points_scored')->default(0); // can be negative
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fantasy_picks');
    }
};
