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
        Schema::create('pdoa_routers_registries_pivot', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdoa_registry_id');
            $table->foreign('pdoa_registry_id')->references('id')->on('pdoa_registries')->onDelete('cascade');
            $table->unsignedBigInteger('pdoa_router_registry_id');
            $table->foreign('pdoa_router_registry_id')->references('id')->on('pdoa_router_registries')->onDelete('cascade');
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
        Schema::dropIfExists('pdoa_routers_registries_pivot');
    }
};
