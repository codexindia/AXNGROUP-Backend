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
        Schema::table('bank_transfers', function (Blueprint $table) {
            // Remove shop_id column if it exists
            if (Schema::hasColumn('bank_transfers', 'shop_id')) {
                $table->dropForeign(['shop_id']);
                $table->dropColumn('shop_id');
            }
            
            // Add optional shop_name field
            $table->string('shop_name')->nullable()->after('customer_mobile');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bank_transfers', function (Blueprint $table) {
            // Remove shop_name column
            $table->dropColumn('shop_name');
            
            // Add back shop_id column
            $table->unsignedBigInteger('shop_id')->nullable()->after('agent_id');
            $table->foreign('shop_id')->references('id')->on('shops')->onDelete('cascade');
            $table->index(['shop_id']);
        });
    }
};
