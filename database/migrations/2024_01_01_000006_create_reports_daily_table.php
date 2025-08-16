<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('reports_daily', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('agent_id');
            $table->date('date');
            $table->integer('onboarding_count');
            $table->integer('bank_transfer_count');
            $table->timestamps();
            
            $table->foreign('agent_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['agent_id']);
            $table->index(['date']);
            $table->unique(['agent_id', 'date']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('reports_daily');
    }
};