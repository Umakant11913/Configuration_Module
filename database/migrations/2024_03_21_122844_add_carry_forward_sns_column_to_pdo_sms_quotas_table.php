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
        Schema::table('pdo_sms_quotas', function (Blueprint $table) {
            $table->integer('carry_forward_sms')->default(NULL)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('pdo_sms_quotas', function (Blueprint $table) {
            $table->dropColumn('carry_forward_sms');
        });
    }
};
