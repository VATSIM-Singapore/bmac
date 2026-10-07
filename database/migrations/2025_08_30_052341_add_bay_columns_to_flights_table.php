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
        Schema::table('flights', function (Blueprint $table) {
            // Add bay-related columns
            $table->unsignedBigInteger('dep_bay')->nullable()->after('arr');
            $table->dateTime('dep_bay_assigned_from')->nullable()->after('dep_bay');
            $table->dateTime('dep_bay_assigned_to')->nullable()->after('dep_bay_assigned_from');
            $table->unsignedBigInteger('arr_bay')->nullable()->after('dep_bay_assigned_to');
            $table->dateTime('arr_bay_assigned_from')->nullable()->after('arr_bay');
            $table->dateTime('arr_bay_assigned_to')->nullable()->after('arr_bay_assigned_from');

            // Add foreign key constraints
            $table->foreign('dep_bay')->references('id')->on('bays')->onDelete('set null');
            $table->foreign('arr_bay')->references('id')->on('bays')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('flights', function (Blueprint $table) {
            // Drop foreign key constraints first
            $table->dropForeign(['dep_bay']);
            $table->dropForeign(['arr_bay']);

            // Drop the columns
            $table->dropColumn([
                'dep_bay',
                'dep_bay_assigned_from',
                'dep_bay_assigned_to',
                'arr_bay',
                'arr_bay_assigned_from',
                'arr_bay_assigned_to'
            ]);
        });
    }
};
