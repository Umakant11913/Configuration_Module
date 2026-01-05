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
        Schema::create('distributor_locations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('dist_id');
            $table->unsignedBigInteger('dist_plan_id');
            $table->integer('no_of_area')->nullable();
            $table->string('area_name')->nullable();
            $table->integer('selected_area')->nullable();
            $table->timestamps();

            $table->foreign('dist_id')->references('id')->on('distributors')->onUpdate('cascade');
            $table->foreign('dist_plan_id')->references('id')->on('distributor_plans')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('distributor_locations');
    }
};
