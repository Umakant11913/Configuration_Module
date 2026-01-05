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
        Schema::table('pdo_network_settings', function (Blueprint $table) {
            $table->integer("subnet_mask")->nullable()->after("essid");
            $table->integer("lease_time")->nullable()->after("subnet_mask");
        });
    }
    
    /**
     * Reverse the migrations.
    *
    * @return void
    */
    public function down()
    {
        Schema::table('pdo_network_settings', function (Blueprint $table) {
            $table->dropColumn("subnet_mask");
            $table->dropColumn("lease_time");
        });
    }
};
