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
            $table->string('support_email')->after('description')->default(NULL)->nullable();
            $table->string('support_phone')->after('support_email')->default(NULL)->nullable();
            $table->string('support_link')->after('support_phone')->default(NULL)->nullable();
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
            $table->dropColumn('support_email');
            $table->dropColumn('support_phone');
            $table->dropColumn('support_link');
        });
    }
};
