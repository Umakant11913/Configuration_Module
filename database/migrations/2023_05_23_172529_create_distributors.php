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
        Schema::create('distributors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_id')->unsigned();
            $table->string('dis_id');
            $table->bigInteger('distributor_plan')->unsigned();
            $table->string('renewal_date');
            $table->string('exclusive');
            $table->string('contract');
            $table->bigInteger('gst_type');
            $table->string('gst_no')->nullable();
            $table->bigInteger('parent_dId')->unsigned();
            $table->timestamps();
            $table->tinyInteger('status')->default('0');

            $table->foreign('owner_id')->references('id')->on('users');
            $table->foreign('distributor_plan')->references('id')->on('distributor_plans');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('distributors');
    }
};
