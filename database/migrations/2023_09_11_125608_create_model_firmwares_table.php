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
        Schema::create('model_firmwares', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('model_id')->unsigned();
            $table->float('firmware_version');
            $table->string('firmware_file');
            $table->boolean('released')->nullable();
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
        Schema::dropIfExists('model_firmwares');
    }
};
