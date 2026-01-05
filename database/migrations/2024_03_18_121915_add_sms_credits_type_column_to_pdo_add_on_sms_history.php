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
        Schema::table('pdo_add_on_sms_history', function (Blueprint $table) {
            $table->dropColumn('add_on_sms');
            $table->integer('sms_credits')->default(NULL)->nullable();
            $table->string('type')->default(NULL)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('pdo_add_on_sms_history', function (Blueprint $table) {
            $table->dropColumn('sms_credits');
            $table->dropColumn('type');
        });
    }
};
