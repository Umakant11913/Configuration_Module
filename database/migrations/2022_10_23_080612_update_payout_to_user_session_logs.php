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
        Schema::table('user_session_logs', function (Blueprint $table) {
            $table->float('payout_ratio', 8, 2)->nullable(true)->default(0.00);
            $table->float('payout_session_duration_ratio', 8, 2)->nullable(true)->default(0.00);
            $table->float('payout_data_ratio', 8, 2)->nullable(true)->default(0.00);
            $table->float('payout_amount', 8, 2)->nullable(true)->default(0.00);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_session_logs', function (Blueprint $table) {
            //
        });
    }
};
