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
        Schema::table('pdoa_plans', function (Blueprint $table) {
            $table->integer('service_fee')->default(null)->nullable();
            $table->integer('contract_length')->default(null)->nullable();
            $table->integer('credits')->default(null)->nullable();
            $table->dateTime('validity_period')->default(null)->nullable();
            $table->bigInteger('sms_quota')->default(null)->nullable();
            $table->integer('grace_period')->default(null)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('pdoa_plans', function (Blueprint $table) {
            $table->dropColumn('service_fee');
            $table->dropColumn('contract_length');
            $table->dropColumn('credits');
            $table->dropColumn('validity_period');
            $table->dropColumn('sms_quota');
            $table->dropColumn('grace_period');
        });
    }
};
