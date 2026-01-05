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
        Schema::table('branding_profiles', function (Blueprint $table) {
            $table->string('privacy_policy')->nullable()->default(null);
            $table->string('terms_conditions')->nullable()->default(null);
            $table->string('banner_url')->after('banner')->nullable()->default(null);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('branding_profiles', function (Blueprint $table) {
            $table->dropColumn('privacy_policy');
            $table->dropColumn('terms_conditions');
            $table->dropColumn('banner_url');
        });
    }
};
