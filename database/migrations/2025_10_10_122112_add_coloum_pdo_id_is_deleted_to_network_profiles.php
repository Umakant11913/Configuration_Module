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
        Schema::table('network_profiles', function (Blueprint $table) {
            $table->integer("pdo_id")->after("name");
            $table->boolean('is_deleted')->default(false)->after('updated_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('network_profiles', function (Blueprint $table) {
            $table->dropColumn(['pdo_id', 'is_deleted']);
        });
    }
};
