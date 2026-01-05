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
        Schema::create('user_session_logs', function (Blueprint $table) {
            $table->id();
            $table->datetime('session_start_time');
            $table->datetime('session_update_time');
            $table->integer('session_type')->nullable(false)->default(0);
            $table->integer('session_duration')->nullable(false)->default(0);
            $table->integer('downloads')->nullable(false)->default(0);
            $table->integer('uploads')->nullable(false)->default(0);
            $table->integer('plan_id')->nullable(false)->default(0);
            $table->integer('plan_price')->nullable(false)->default(0);
            $table->integer('plan_duration')->nullable(false)->default(0);
            $table->integer('paymnent_id')->nullable(false)->default(0);
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
        Schema::dropIfExists('session_logs');
    }
};
