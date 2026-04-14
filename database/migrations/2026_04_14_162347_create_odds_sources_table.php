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
        Schema::create('odds_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique()->comment('e.g., the-odds-api.com');
            $table->string('api_url')->comment('Base API URL');
            $table->string('api_key')->comment('API authentication key');
            $table->boolean('is_active')->default(true);
            $table->dateTime('last_synced_at')->nullable();
            $table->integer('sync_interval_minutes')->default(60)->comment('Minutes between syncs');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('odds_sources');
    }
};
