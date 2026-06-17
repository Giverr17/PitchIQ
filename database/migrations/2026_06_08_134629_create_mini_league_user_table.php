<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::create('mini_league_user', function (Blueprint $table) {
        $table->id();
        $table->foreignId('mini_league_id')->constrained()->cascadeOnDelete();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->timestamps();
        $table->unique(['mini_league_id', 'user_id']); // can't join the same league twice
    });
}

public function down(): void
{
    Schema::dropIfExists('mini_league_user');
}
};
