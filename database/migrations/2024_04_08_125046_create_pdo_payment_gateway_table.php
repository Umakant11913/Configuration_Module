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
        Schema::create('pdo_payment_gateway', function (Blueprint $table) {
            $table->id();
            $table->integer('pdo_id')->default(NULL)->nullable();
            $table->integer('zone_id')->default(NULL)->nullable();
            $table->string('secret')->default(NULL)->nullable();
            $table->string('key')->default(NULL)->nullable();
            $table->string('providers')->default(NULL)->nullable();
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
        Schema::dropIfExists('pdo_payment_gateway');
    }
};
