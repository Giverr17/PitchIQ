<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['top_scorer', 'most_assists']);
            $table->foreignId('predicted_player_id')
                  ->nullable()
                  ->constrained('players')
                  ->nullOnDelete();
            $table->unsignedSmallInteger('points_earned')->default(0);
            $table->timestamp('verified_at')->nullable();
            $table->unique(['user_id', 'tournament_id', 'type']); // one pick per type per user per tournament
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_predictions');
    }
};
