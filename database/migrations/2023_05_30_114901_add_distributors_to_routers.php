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
        Schema::table('routers', function (Blueprint $table) {
            $table->bigInteger('pdo_id')->unsigned()->nullable();
            $table->bigInteger('distributor_id')->unsigned()->nullable();
            $table->string('distributor_service_type')->nullable();
            // $table->string('distributor_service')->nullable();

            $table->foreign('pdo_id')->references('id')->on('users')->onUpdate('cascade');
            $table->foreign('distributor_id')->references('id')->on('distributors')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('routers', function (Blueprint $table) {
            //
        });
    }
};
