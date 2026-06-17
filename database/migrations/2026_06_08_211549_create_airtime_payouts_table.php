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
        Schema::create('airtime_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tournament_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('matchday')->nullable();   // null = tournament-level payout
            $table->string('scope');                           // 'matchday' | 'tournament'
            $table->unsignedSmallInteger('rank');              // 1, 2, or 3
            $table->string('phone', 20);
            $table->unsignedInteger('amount');                 // naira
            $table->string('status')->default('pending');      // pending | success | failed
            $table->string('provider_reference')->nullable();  // VTU transaction id
            $table->text('notes')->nullable();                  // error messages etc.
            $table->timestamps();

            // Prevent paying the same user twice for the same event+rank
            $table->unique(['tournament_id', 'matchday', 'scope', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('airtime_payouts');
    }
};
