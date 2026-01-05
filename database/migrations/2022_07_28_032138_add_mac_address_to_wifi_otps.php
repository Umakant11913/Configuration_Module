<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMacAddressToWifiOtps extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('wifi_otps', function (Blueprint $table) {
            $table->string('mac_address');
            $table->integer('status')->default(0);
            $table->string('url_code');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('wifi_otps', function (Blueprint $table) {
            //
        });
    }
}
