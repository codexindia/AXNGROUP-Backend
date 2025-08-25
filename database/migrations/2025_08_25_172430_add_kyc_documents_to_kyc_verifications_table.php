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
        Schema::table('kyc_verifications', function (Blueprint $table) {
            // Aadhaar Card Details
            $table->string('aadhar_number', 12)->nullable()->after('user_id');
            $table->string('aadhar_photo')->nullable()->after('aadhar_number');
            
            // PAN Card Details
            $table->string('pan_number', 10)->nullable()->after('aadhar_photo');
            $table->string('pan_photo')->nullable()->after('pan_number');
            
            // Bank Details
            $table->string('bank_account_number')->nullable()->after('pan_photo');
            $table->string('bank_ifsc_code', 11)->nullable()->after('bank_account_number');
            $table->string('bank_name')->nullable()->after('bank_ifsc_code');
            $table->string('account_holder_name')->nullable()->after('bank_name');
            $table->string('passbook_photo')->nullable()->after('account_holder_name');
            
            // User Profile Photo
            $table->string('profile_photo')->nullable()->after('passbook_photo');
            
            // Working City
            $table->string('working_city')->nullable()->after('profile_photo');
            
            // Additional fields
            $table->timestamp('submitted_at')->nullable()->after('working_city');
            $table->timestamp('approved_at')->nullable()->after('submitted_at');
            $table->unsignedBigInteger('approved_by')->nullable()->after('approved_at');
            
            // Add foreign key for approved_by
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            
            // Add indexes
            $table->index(['aadhar_number']);
            $table->index(['pan_number']);
            $table->index(['submitted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kyc_verifications', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropIndex(['aadhar_number']);
            $table->dropIndex(['pan_number']);
            $table->dropIndex(['submitted_at']);
            
            $table->dropColumn([
                'aadhar_number',
                'aadhar_photo',
                'pan_number',
                'pan_photo',
                'bank_account_number',
                'bank_ifsc_code',
                'bank_name',
                'account_holder_name',
                'passbook_photo',
                'profile_photo',
                'working_city',
                'submitted_at',
                'approved_at',
                'approved_by'
            ]);
        });
    }
};
