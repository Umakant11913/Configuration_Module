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
        Schema::table('network_settings', function (Blueprint $table) {
            $table->json('user_dns')->default(NULL)->nullable();
            $table->json('device_dns')->default(NULL)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('network_settings', function (Blueprint $table) {
            $table->dropColumn('user_dns');
            $table->dropColumn('device_dns');
        });
    }
};
