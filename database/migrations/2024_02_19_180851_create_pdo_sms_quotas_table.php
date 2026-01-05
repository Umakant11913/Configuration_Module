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
        Schema::create('pdo_sms_quotas', function (Blueprint $table) {
            $table->id();
            $table->integer('pdo_id')->default(null)->nullable();
            $table->integer('sms_quota')->default(null)->nullable();
            $table->integer('sms_used')->default(null)->nullable();
            $table->string('period_type')->nullable()->default(null);
            $table->dateTime('start_date')->default(null)->nullable();
            $table->dateTime('end_date')->default(null)->nullable();
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
        Schema::dropIfExists('pdo_sms_quotas');
    }
};
