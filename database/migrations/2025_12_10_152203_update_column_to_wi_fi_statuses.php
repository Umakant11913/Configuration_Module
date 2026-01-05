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
        Schema::table('wi_fi_statuses', function (Blueprint $table) {
            $table->integer('client_2g')->default(0);
            $table->integer('client_5g')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('wi_fi_statuses', function (Blueprint $table) {
            $table->dropColumn(['client_2g', 'client_5g']);
        });
    }
};
