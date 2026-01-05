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
        Schema::table('internet_plans_vouchers', function (Blueprint $table) {
            $table->boolean('allocated_to_user')->default(false)->nullable();
            $table->string('allocated_phone_number')->default(NULL)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('internet_plans_vouchers', function (Blueprint $table) {
            $table->dropColumn('allocated_to_user');
            $table->dropColumn('allocated_phone_number');
        });
    }
};
