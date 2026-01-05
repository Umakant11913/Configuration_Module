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
        Schema::create('internet_plans_vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('status');
            $table->date('expiry_date');
            $table->integer('location_id')->default(NULL)->nullable();
            $table->integer('plan_id')->default(NULL)->nullable();
            $table->integer('pdo_id')->default(NULL)->nullable();
            $table->integer('zone_id')->default(NULL)->nullable();
            $table->integer('user_id')->default(NULL)->nullable();
            $table->date('used_on')->default(NULL)->nullable();;
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
        Schema::dropIfExists('internet_plans_vouchers');
    }
};
