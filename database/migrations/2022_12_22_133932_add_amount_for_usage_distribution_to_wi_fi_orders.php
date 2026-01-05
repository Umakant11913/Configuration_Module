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
        Schema::table('wi_fi_orders', function (Blueprint $table) {
            $table->float('amount_for_usage_distribution', 8, 2)->nullable(false)->default(0.00);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('wi_fi_orders', function (Blueprint $table) {
            //
        });
    }
};
