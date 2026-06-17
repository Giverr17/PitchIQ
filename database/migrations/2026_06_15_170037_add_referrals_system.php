<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add referral fields to users
        Schema::table('users', function (Blueprint $table) {
            $table->string('referral_code', 12)->nullable()->unique()->after('is_admin');
            $table->unsignedBigInteger('referred_by')->nullable()->after('referral_code');

            $table->foreign('referred_by')->references('id')->on('users')->nullOnDelete();
        });

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
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['referred_by']);
            $table->dropColumn(['referral_code', 'referred_by']);
        });
        Schema::dropIfExists('referrals');
    }
};