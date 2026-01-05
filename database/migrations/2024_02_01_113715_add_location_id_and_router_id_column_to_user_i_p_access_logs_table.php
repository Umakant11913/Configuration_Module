<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_i_p_access_logs', function (Blueprint $table) {
            $table->integer('location_id')->default(NULL)->nullable();
            $table->integer('router_id')->default(NULL)->nullable();
            $table->string('user_mac_address')->default(NULL)->nullable();

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_i_p_access_logs', function (Blueprint $table) {
            $table->dropColumn('location_id');
            $table->dropColumn('router_id');
            $table->dropColumn('user_mac_address');
        });
    }
};
