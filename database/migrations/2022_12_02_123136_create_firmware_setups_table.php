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
        Schema::create('firmware_setups', function (Blueprint $table) {
            $table->id();
            $table->string('file_name')->nullable(false);
            $table->string('file_hash')->nullable(false);
            $table->float('fw_version_no', 8, 2)->nullable(false)->default(0.00);
            $table->integer('enabled')->nullable(false)->default(1);
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
        Schema::dropIfExists('firmware_setups');
    }
};
