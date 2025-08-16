<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('wallet_id');
            $table->enum('type', ['credit', 'debit']);
            $table->decimal('amount', 12, 2);
            $table->string('reference_type')->comment('e.g. bank_transfer, withdrawal');
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('remark')->nullable();
            $table->timestamps();
            
            $table->foreign('wallet_id')->references('id')->on('wallets')->onDelete('cascade');
            $table->index(['wallet_id']);
            $table->index(['type']);
            $table->index(['reference_type']);
            $table->index(['reference_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('wallet_transactions');
    }
};