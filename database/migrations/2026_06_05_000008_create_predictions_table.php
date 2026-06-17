<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fixture_id')->constrained()->cascadeOnDelete();
            $table->enum('predicted_result', ['home', 'draw', 'away'])->nullable();
            $table->unsignedTinyInteger('predicted_home_score')->nullable();
            $table->unsignedTinyInteger('predicted_away_score')->nullable();
            $table->foreignId('predicted_scorer_id')->nullable()->constrained('players')->nullOnDelete();
            $table->unsignedSmallInteger('points_earned')->default(0);
            $table->timestamp('verified_at')->nullable();
            $table->unique(['user_id', 'fixture_id']); // one prediction per fixture per user
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('predictions');
    }
};
