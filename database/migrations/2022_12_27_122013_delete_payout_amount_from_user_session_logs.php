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
            $table->dropColumn('payout_amount');
            $table->float('pdo_payout_amount', 8, 2)->nullable(false)->default(0.00);
            $table->float('pdoa_payout_amount', 8, 2)->nullable(false)->default(0.00);
            $table->float('distributor_payout_amount', 8, 2)->nullable(false)->default(0.00);
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
