<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fixture_player_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fixture_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('minutes_played')->default(0);
            $table->unsignedTinyInteger('saves')->default(0);
            $table->tinyInteger('bonus')->default(0);
            $table->unique(['fixture_id', 'player_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixture_player_stats');
    }
};
