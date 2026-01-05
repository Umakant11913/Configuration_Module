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
        Schema::create('threshold_limit_notifications', function (Blueprint $table) {
            $table->id();
            $table->integer('pdo_id')->nullable(false);
            $table->integer('payout_id')->nullable(false);
            $table->string('first_name')->nullable(false)->default(null);
            $table->string('last_name')->nullable(false)->default(null);
            $table->string('subject')->nullable(false)->default(null);
            $table->text('body')->nullable(false)->default(null);
            $table->string('payout_amount')->nullable(false)->default(0);
            $table->string('mail_sent')->nullable(false)->default(null);
            $table->string('approved_status')->nullable(false)->default(null);
            $table->string('payment_status')->nullable(false)->default(null);
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
        Schema::dropIfExists('threshold_limit_notification');
    }
};
