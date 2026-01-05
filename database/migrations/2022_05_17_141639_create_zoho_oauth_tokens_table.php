<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('zoho_oauth_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('state', 20);
            $table->string('account_domain')->nullable();
            $table->string('grant_code')->nullable();
            $table->string('location')->nullable();
            $table->string('accounts_server')->nullable();
            $table->string('refresh_token')->nullable();
            $table->string('access_token')->nullable();
            $table->string('expiry_time', 20)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('zoho_oauth_tokens');
    }
};
