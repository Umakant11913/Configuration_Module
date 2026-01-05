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
        Schema::create('pdo_sms_history', function (Blueprint $table) {
            $table->id();
            $table->integer('pdo_id')->default(null)->nullable();
            $table->integer('user_id')->default(null)->nullable();
            $table->string('phone')->default(null)->nullable();
            $table->integer('quota_id')->default(null)->nullable();
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
        Schema::dropIfExists('pdo_sms_history');
    }
};
