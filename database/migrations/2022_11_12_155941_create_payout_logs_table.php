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
        Schema::create('payout_logs', function (Blueprint $table) {
            $table->id();
            $table->integer('pdo_owner_id')->nullable(false);
            $table->datetime('payout_calculation_date')->nullable(false);
            $table->datetime('payout_date')->nullable();
            $table->float('payout_amount', 8, 2)->nullable(false)->default(0.00);
            $table->integer('payout_status')->nullable(false)->default(0);
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
        Schema::dropIfExists('payout_logs');
    }
};
