<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('adhoc_flights', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('event_id');
            $table->string('callsign');
            $table->string('acType');
            $table->unsignedInteger('dep')->nullable();
            $table->unsignedInteger('arr')->nullable();
            $table->unsignedBigInteger('bay_id')->nullable();
            $table->dateTime('bay_assigned_from')->nullable();
            $table->dateTime('bay_assigned_to')->nullable();
            $table->dateTime('std')->nullable();
            $table->dateTime('sta')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('event_id')->references('id')->on('events')->onDelete('cascade');
            $table->foreign('dep')->references('id')->on('airports');
            $table->foreign('arr')->references('id')->on('airports');
            $table->foreign('bay_id')->references('id')->on('bays');

            // Ensure callsign is unique for a particular event_id
            $table->unique(['event_id', 'callsign']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('adhoc_flights');
    }
};
