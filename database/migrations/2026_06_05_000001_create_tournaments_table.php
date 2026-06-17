<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['faculty_cup', 'departmental_league', 'friendly'])->default('faculty_cup');
            $table->string('season'); // e.g. "2024/2025"
            $table->enum('status', ['upcoming', 'active', 'completed'])->default('upcoming');
            $table->date('start_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournaments');
    }
};
