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
        Schema::table('match_odds', function (Blueprint $table) {
            $table->dropUnique('unique_match_source_type');
            $table->unique(['match_id', 'odds_source_id', 'odds_type', 'bookmaker_name'], 'unique_match_source_type_bookmaker');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('match_odds', function (Blueprint $table) {
            $table->dropUnique('unique_match_source_type_bookmaker');
            $table->unique(['match_id', 'odds_source_id', 'odds_type'], 'unique_match_source_type');
        });
    }
};
