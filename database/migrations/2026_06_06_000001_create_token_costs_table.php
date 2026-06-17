<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('token_costs', function (Blueprint $table) {
            $table->id();
            $table->string('feature')->unique();        // e.g. 'prediction', 'squad_builder', 'game'
            $table->string('label');                    // human-readable name shown in admin
            $table->string('description')->nullable();  // optional helper text
            $table->unsignedInteger('cost')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('token_costs');
    }
};
