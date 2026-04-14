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
        Schema::create('match_odds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('sports_matches')->onDelete('cascade');
            $table->foreignId('odds_source_id')->constrained('odds_sources')->onDelete('cascade');
            $table->enum('odds_type', ['1', 'X', '2'])->comment('1=Home Win, X=Draw, 2=Away Win');
            $table->decimal('odds_value', 8, 2)->comment('Decimal odds value');
            $table->string('bookmaker_name')->nullable()->comment('Specific bookmaker within the source');
            $table->timestamps();
            $table->index(['match_id', 'odds_source_id']);
            $table->unique(['match_id', 'odds_source_id', 'odds_type'], 'unique_match_source_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('match_odds');
    }
};
