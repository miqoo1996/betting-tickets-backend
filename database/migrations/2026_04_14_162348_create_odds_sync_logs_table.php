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
        Schema::create('odds_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('odds_source_id')->constrained('odds_sources')->onDelete('cascade');
            $table->integer('total_matches_synced')->default(0);
            $table->integer('total_odds_synced')->default(0);
            $table->enum('status', ['success', 'failed', 'partial'])->default('success');
            $table->text('error_message')->nullable();
            $table->dateTime('synced_at')->comment('When sync was performed');
            $table->timestamps();
            $table->index('odds_source_id');
            $table->index('synced_at');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('odds_sync_logs');
    }
};
