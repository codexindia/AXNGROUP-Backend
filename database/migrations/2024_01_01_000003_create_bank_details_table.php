<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('bank_details', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->string('account_holder_name');
            $table->string('bank_name');
            $table->string('account_number');
            $table->string('confirm_account_number');
            $table->string('ifsc_code');
            $table->boolean('is_verified')->default(false);
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id']);
            $table->index(['is_verified']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('bank_details');
    }
};