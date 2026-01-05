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
        Schema::table('user_i_p_access_logs', function (Blueprint $table) {

		$table->string('username');
		$table->string('src_port')->nullable();
		$table->string('dest_port')->nullable();
		$table->string('client_device_ip')->nullable();
		$table->string('client_device_ip_type')->nullable();
		$table->string('client_device_translated_ip')->nullable();
		/*$table->string('')->nullable();
		$table->string('')->nullable();
		 */
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_i_p_access_logs', function (Blueprint $table) {
            //
        });
    }
};
