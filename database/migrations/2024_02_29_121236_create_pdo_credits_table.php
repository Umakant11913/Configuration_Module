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
        Schema::create('pdo_credits', function (Blueprint $table) {
            $table->id();
            $table->integer('pdo_id');
            $table->bigInteger('credits')->default(null)->nullable();
            $table->bigInteger('used_credits')->default(null)->nullable();
            $table->dateTime('expiry_date')->default(null)->nullable();
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
        Schema::dropIfExists('pdo_credits');
    }

};
