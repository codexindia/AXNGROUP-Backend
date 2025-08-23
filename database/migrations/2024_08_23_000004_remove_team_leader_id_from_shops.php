<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('shops', function (Blueprint $table) {
            // Drop the foreign key and index first
            $table->dropForeign(['team_leader_id']);
            $table->dropIndex(['team_leader_id']);
            $table->dropColumn('team_leader_id');
        });
    }

    public function down()
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->unsignedBigInteger('team_leader_id')->after('agent_id');
            $table->foreign('team_leader_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['team_leader_id']);
        });
    }
};
