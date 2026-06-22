<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fixture_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->string('event_type'); // validated against App\Enums\PlayerEventType
            $table->unsignedTinyInteger('minute')->nullable();
            $table->boolean('is_substitute')->default(false); // was player a sub when event happened
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_events');
    }
};
