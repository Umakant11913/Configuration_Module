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
        Schema::table('notification_settings', function (Blueprint $table) {
            $table->json('frequency')->default(NULL)->nullable();
            $table->string('day')->default(NULL)->nullable();
            $table->time('time')->default(NULL)->nullable();
            $table->date('date')->default(NULL)->nullable();
            $table->json('channel')->default(NULL)->nullable();
            $table->integer('recipient_id')->default(NULL)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('notification_settings',function (Blueprint $table) {
            $table->dropColumn('frequency');
            $table->dropColumn('day');
            $table->dropColumn('time');
            $table->dropColumn('date');
            $table->dropColumn('channel');
            $table->dropColumn('recipient_id');

        });
    }
};
