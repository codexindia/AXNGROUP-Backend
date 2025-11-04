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
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->date('joining_date')->nullable()->after('dob')->comment('User joining/registration date');
            $table->date('id_card_valid_until')->nullable()->after('joining_date')->comment('ID card expiry date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn(['joining_date', 'id_card_valid_until']);
        });
    }
};
