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
        Schema::create('pdo_bank_details', function (Blueprint $table) {
            $table->id();
            $table->integer('pdo_owner_id')->nullable(false);
            $table->string('name')->nullable(false);
            $table->string('bank_name')->nullable(false);
            $table->string('account_number')->nullable(false);
            $table->string('branch')->nullable(false);
            $table->string('account_type')->nullable(false);
            $table->string('ifsc_code')->nullable(false);
            $table->integer('bank_status')->nullable(false)->default(0);
            $table->integer('is_primary')->nullable(false)->default(0);
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
        Schema::dropIfExists('pdo_bank_details');
    }
};
