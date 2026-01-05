<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('routers', function (Blueprint $table) {
            $table->integer('upload_speed')->default(0);
            $table->integer('download_speed')->default(0);
        });
    }

    public function down()
    {
        Schema::table('routers', function (Blueprint $table) {
            $table->dropColumn('upload_speed', 'download_speed');
        });
    }
};
