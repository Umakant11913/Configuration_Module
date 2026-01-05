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
        Schema::table('locations', function (Blueprint $table) {
            $table->integer('snmp_profile_id')->nullable()->after('profile_id');
            $table->integer('ntp_profile_id')->nullable()->after('snmp_profile_id');
            $table->integer('qos_profile_id')->nullable()->after('ntp_profile_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn('snmp_profile_id');
            $table->dropColumn('ntp_profile_id');
            $table->dropColumn('qos_profile_id');
        });
    }
};
