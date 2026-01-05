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
        Schema::create('router_status', function (Blueprint $table) {
            $table->id();
            $table->integer('router_id');
            $table->integer('pdo_id');
            $table->integer('router_status');
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
        Schema::table('router_status', function (Blueprint $table) {
            $table->dropColumn('router_id');
            $table->dropColumn('pdo_id');
            $table->dropColumn('router_status');
        });
    }
};
