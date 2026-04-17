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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->json('bets')->comment('JSON array of selected bets');
            $table->decimal('stake', 10, 2)->comment('Stake amount in EUR');
            $table->decimal('total_odds', 10, 4)->comment('Combined odds');
            $table->decimal('potential_winning', 10, 2)->comment('Potential winning amount');
            $table->string('status')->default('pending')->comment('pending, won, lost, cancelled');
            $table->string('ticket_number')->unique()->comment('Unique ticket identifier');
            $table->text('notes')->nullable()->comment('User notes or ticket description');
            $table->timestamps();
            
            // Indexes
            $table->index('user_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
