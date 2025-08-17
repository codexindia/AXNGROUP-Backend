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
        Schema::table('shops', function (Blueprint $table) {
            $table->decimal('payout_amount', 10, 2)->nullable()->after('reject_remark');
            $table->boolean('payout_processed')->default(false)->after('payout_amount');
            $table->string('admin_remark')->nullable()->after('payout_processed');
            $table->timestamp('admin_approved_at')->nullable()->after('admin_remark');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn(['payout_amount', 'payout_processed', 'admin_remark', 'admin_approved_at']);
        });
    }
};
