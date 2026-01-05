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
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('powered_by')->default(NULL)->nullable();
            $table->boolean('is_pmwani_user')->default(NULL)->nullable();
            $table->bigInteger('location_id')->default(0)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('powered_by');
            $table->dropColumn('is_pmwani_user');
            $table->dropColumn('location_id');
        });
    }
};
