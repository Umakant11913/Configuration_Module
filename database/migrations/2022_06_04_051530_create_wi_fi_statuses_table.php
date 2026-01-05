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
        Schema::create('wi_fi_statuses', function (Blueprint $table) {
            $table->id();
            $table->integer('wifi_router_id')->nullable(false);
            $table->integer('cpu_usage')->nullable();
            $table->integer('disk_usage')->nullable();
            $table->integer('ram_usage')->nullable();
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
        Schema::dropIfExists('wi_fi_statuses');
    }
};
