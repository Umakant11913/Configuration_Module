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
        Schema::create('gst_files', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('distributor_id')->unsigned();
            $table->string('photo')->nullable();
            $table->string('address_proof')->nullable();
            $table->string('id_proof')->nullable();
            $table->string('company_proof')->nullable();
            $table->string('bank_details')->nullable();
            $table->timestamps();

            $table->foreign('distributor_id')->references('id')->on('distributors');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gst_files');
    }
};
