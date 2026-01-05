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
        Schema::create('zoho_user_ids', function (Blueprint $table) {
            $table->id();
            $table->string('contact_id');
            $table->unsignedBigInteger('user_id');
            $table->string('vendor_name')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('contact_name')->nullable();
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
        Schema::dropIfExists('zoho_user_ids');
    }
};
