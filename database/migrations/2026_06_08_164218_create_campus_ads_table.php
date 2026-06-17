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
        Schema::create('campus_ads', function (Blueprint $table) {
            $table->id();
            $table->string('business_name');
            $table->string('image_path');           // banner image
            $table->string('link_url')->nullable(); // where the banner clicks to
            $table->boolean('is_active')->default(true);
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->unsignedInteger('clicks')->default(0); // track engagement
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campus_ads');
    }
};
