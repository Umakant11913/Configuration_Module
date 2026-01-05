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
        Schema::create('zoho_orders', function (Blueprint $table) {
            $table->id();
            $table->string('salesorder_number')->nullable();
            $table->string('order_status')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('total_formatted')->nullable();
            $table->string('total')->nullable();
            $table->date('date')->nullable();
            $table->string('customer_id')->nullable();
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
        Schema::dropIfExists('zoho_orders');
    }
};
