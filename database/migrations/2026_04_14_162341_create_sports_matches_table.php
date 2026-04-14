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
        Schema::create('sports_matches', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->unique()->comment('ID from external API');
            $table->string('league')->comment('e.g., Premier League, La Liga');
            $table->string('home_team');
            $table->string('away_team');
            $table->dateTime('commence_time')->comment('Match start time');
            $table->enum('status', ['scheduled', 'live', 'finished', 'cancelled'])->default('scheduled');
            $table->dateTime('synced_at')->nullable()->comment('Last time data was synced from API');
            $table->timestamps();
            $table->index('league');
            $table->index('commence_time');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sports_matches');
    }
};
