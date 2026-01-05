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
        Schema::create('mqtt_aps_live_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('mac')->nullable();
            $table->string('status')->nullable();
            $table->longText('json_data')->nullable();
            $table->dateTime('setting_update_at')->nullable();
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
        Schema::dropIfExists('mqtt_aps_live_statuses');
    }
};
