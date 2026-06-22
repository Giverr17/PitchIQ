<?php

use App\Enums\TournamentStatus;
use App\Enums\TournamentType;
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
            $table->string('type')->default(TournamentType::FacultyCup->value);
            $table->string('season'); // e.g. "2024/2025"
            $table->string('status')->default(TournamentStatus::Upcoming->value);
            $table->unsignedInteger('active_matchday')->default(1);
            $table->unsignedTinyInteger('squad_size')->default(11);
            $table->date('start_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournaments');
    }
};
