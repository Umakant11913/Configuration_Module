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
        Schema::create('wifi_configuration_profiles', function (Blueprint $table) {
            $table->id();
            $table->text('title');
            $table->bigInteger('pdo_id');
            $table->json('settings');
            $table->boolean('disabled');
            $table->boolean('published');
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
        Schema::dropIfExists('wifi_configuration_profiles');
    }
};
