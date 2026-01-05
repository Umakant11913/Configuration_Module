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
            $table->dropColumn('period_type');
            $table->dropColumn('start_date');
            $table->dropColumn('end_date');
            $table->bigInteger('add_on_sms')->default(null)->nullable();
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
            $table->dropColumn('add_on_sms');
        });
    }
};
