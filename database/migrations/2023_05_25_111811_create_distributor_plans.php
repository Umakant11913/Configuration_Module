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
        Schema::create('distributor_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('area_name');
            $table->integer('number_of_area');
            $table->integer('number_of_device');
            $table->string('target_type');
            $table->integer('target_device');
            // $table->bigInteger('pdo_id')->unsigned();
            $table->string('pdo_id');
            $table->timestamps();

            // $table->foreign('pdo_id')->references('id')->on('pdoa_plans');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('distributor_plans');
    }
};
