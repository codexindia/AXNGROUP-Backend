<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('bank_transfers', function (Blueprint $table) {
            $table->unsignedBigInteger('shop_id')->nullable()->after('team_leader_id');
            $table->foreign('shop_id')->references('id')->on('shops')->onDelete('set null');
            $table->index(['shop_id']);
        });
    }

    public function down()
    {
        Schema::table('bank_transfers', function (Blueprint $table) {
            $table->dropForeign(['shop_id']);
            $table->dropIndex(['shop_id']);
            $table->dropColumn('shop_id');
        });
    }
};
