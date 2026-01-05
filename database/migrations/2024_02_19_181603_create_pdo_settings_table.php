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
        Schema::create('pdo_settings', function (Blueprint $table) {
            $table->id();
            $table->integer('pdo_id');
            $table->integer('period_quota')->nullable()->default(null);
            $table->string('period_type')->nullable()->default(null);
            $table->bigInteger('add_on_available')->nullable()->default(null);
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
        Schema::dropIfExists('pdo_settings');
    }
};
