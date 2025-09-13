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
        Schema::create('bay_blockings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('event_id');
            $table->unsignedBigInteger('bay_id');
            $table->timestamps();
            
            // Foreign key constraints
            $table->foreign('event_id')->references('id')->on('events')->onDelete('cascade');
            $table->foreign('bay_id')->references('id')->on('bays')->onDelete('cascade');
            
            // Ensure one bay can only be blocked once per event
            $table->unique(['event_id', 'bay_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bay_blockings');
    }
};
