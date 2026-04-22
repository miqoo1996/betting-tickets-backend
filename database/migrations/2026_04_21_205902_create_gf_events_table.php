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
        Schema::create('gf_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('external_id')->unique();
            $table->foreignId('league_id')->constrained('gf_leagues');
            $table->foreignId('home_team_id')->constrained('gf_teams');
            $table->foreignId('away_team_id')->constrained('gf_teams');
            $table->string('status_code', 20)->default('SCHEDULED')->index();
            $table->string('round')->nullable();
            $table->string('referee')->nullable();
            $table->timestamp('start_at')->nullable()->index();
            $table->json('score')->nullable();
            $table->json('bookmaker_odds')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index('league_id');
            $table->index('home_team_id');
            $table->index('away_team_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gf_events');
    }
};
