<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('shops', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('agent_id');
            $table->unsignedBigInteger('team_leader_id');
            $table->string('customer_name');
            $table->string('customer_mobile');
            $table->enum('status', ['pending', 'approved', 'rejected']);
            $table->string('reject_remark')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('agent_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('team_leader_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['agent_id']);
            $table->index(['team_leader_id']);
            $table->index(['status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('shops');
    }
};