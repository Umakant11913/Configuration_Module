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
        Schema::create('pdo_agreement_details', function (Blueprint $table) {
            $table->id();
            $table->integer('pdo_owner_id')->nullable(false);
            $table->integer('router_id')->nullable(false);
            $table->string('revenue_share')->nullable(false);
            $table->string('subscription_data')->nullable(false);
            $table->string('sms_quota')->nullable(false);
            $table->string('storage_quota')->nullable(false);
            $table->string('email_quota')->nullable(false);
            $table->string('latitude')->nullable(false);
            $table->string('longitude')->nullable(false);
            $table->datetime('expiry_date')->nullable();
            $table->datetime('start_date')->nullable();
            $table->datetime('end_date')->nullable();
            $table->integer('agreement_status')->nullable(false)->default(0);
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
        Schema::dropIfExists('pdo_agreement_details');
    }
};
