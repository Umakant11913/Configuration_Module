<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pdoa_router_registries', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default(NULL)->nullable();
            $table->string('state')->default(NULL)->nullable();
            $table->string('geoLoc')->default(NULL)->nullable();
            $table->string('macid')->default(NULL)->nullable();
            $table->string('ssid')->default(NULL)->nullable();
            $table->string('status')->default(NULL)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('pdoa_router_registries', function (Blueprint $table) {
            $table->dropColumn('name');
            $table->dropColumn('state');
            $table->dropColumn('geoLoc');
            $table->dropColumn('macid');
            $table->dropColumn('ssid');
            $table->dropColumn('status');
        });
    }
};
