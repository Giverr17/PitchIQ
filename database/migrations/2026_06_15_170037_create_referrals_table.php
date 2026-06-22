<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void 
    {
        // referral_code + referred_by now live in the create_users migration.
        // Track each referral and its reward status
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('referred_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('pending');   // pending -> completed
            $table->timestamp('rewarded_at')->nullable();
            $table->timestamps();

            $table->unique('referred_id');   // a user can only be referred once
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};