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
        Schema::create('gf_predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->unique()->constrained('gf_events')->cascadeOnDelete();
            $table->json('match_result')->nullable();
            $table->json('total_goals')->nullable();
            $table->json('home_team_goals')->nullable();
            $table->json('away_team_goals')->nullable();
            $table->json('both_teams_score')->nullable();
            $table->json('first_half_winner')->nullable();
            $table->json('exact_score')->nullable();
            $table->json('recommended_bets')->nullable();
            $table->json('reasoning')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gf_predictions');
    }
};
