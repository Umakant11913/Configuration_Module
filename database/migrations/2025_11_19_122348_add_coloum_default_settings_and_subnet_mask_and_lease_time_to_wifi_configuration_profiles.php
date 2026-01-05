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
        Schema::table('wifi_configuration_profiles', function (Blueprint $table) {
            $table->longtext('advance_setting')->nullable()->after('settings');
            $table->string('essid')->nullable()->after('advance_setting');
            $table->string('subnet_mask')->nullable()->after('essid');
            $table->integer('lease_time')->nullable()->after('subnet_mask');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('wifi_configuration_profiles', function (Blueprint $table) {
            $table->dropColumn('advance_setting');
            $table->dropColumn('essid');
            $table->dropColumn('subnet_mask');
            $table->dropColumn('lease_time');
        });
    }
};
