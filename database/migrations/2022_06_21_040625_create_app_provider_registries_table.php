<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('app_provider_registries', function (Blueprint $table) {
            $table->id();
            $table->string('provider_id');
            $table->text('auth_url');
            $table->string('email');
            $table->string('name');
            $table->string('rating');
            $table->string('status');
            $table->string('phone');
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
        Schema::dropIfExists('app_provider_registries');
    }
};
