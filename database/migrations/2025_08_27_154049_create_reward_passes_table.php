<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reward_passes', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('agent_id');
            $table->string('customer_name');
            $table->string('customer_mobile', 15);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->string('reject_remark')->nullable();
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('agent_id')->references('id')->on('users')->onDelete('cascade');
            
            // Indexes
            $table->index(['agent_id']);
            $table->index(['status']);
            $table->index(['customer_mobile']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reward_passes');
    }
};
