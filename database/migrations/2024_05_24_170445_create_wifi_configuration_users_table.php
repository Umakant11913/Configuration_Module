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
        Schema::create('wifi_configuration_users', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('pdo_id');
            $table->string('user_id');
            $table->string('full_name')->nullable()->default(NULL);
            $table->text('description')->nullable();
            $table->string('password')->nullable()->default(NULL);
            $table->text('email')->nullable();
            $table->dateTime('expiry_date')->nullable();
            $table->string('type')->nullable()->default('');
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
        Schema::dropIfExists('wifi_configuration_users');
    }
};
