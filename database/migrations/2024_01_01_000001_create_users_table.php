<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->string('unique_id')->unique()->comment('Format: AXN00001');
            $table->string('name');
            $table->string('mobile')->unique();
            $table->string('email')->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->enum('role', ['agent', 'leader', 'admin', 'office_staff']);
            $table->string('referral_code')->nullable();
            $table->boolean('is_blocked')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['role']);
            $table->index(['is_blocked']);
            $table->index(['referral_code']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('users');
    }
};
