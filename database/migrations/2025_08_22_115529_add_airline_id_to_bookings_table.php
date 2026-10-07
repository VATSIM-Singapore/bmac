<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddAirlineIdToBookingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedInteger('airline_id')->nullable()->after('user_id');
            $table->foreign('airline_id')->references('id')->on('airlines')->onDelete('set null');
            $table->index('airline_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['airline_id']);
            $table->dropIndex(['airline_id']);
            $table->dropColumn('airline_id');
        });
    }
}
