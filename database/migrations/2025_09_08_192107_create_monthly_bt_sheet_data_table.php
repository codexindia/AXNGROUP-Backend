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
        Schema::create('monthly_bt_sheet_data', function (Blueprint $table) {
            $table->id();
            $table->string('cus_name'); // Customer Name
            $table->string('mobile_no'); // Mobile Number
            $table->decimal('total_bank_transfer', 12, 2); // Total Bank Transfer amount
            $table->year('year'); // Year
            $table->tinyInteger('month'); // Month (1-12)
            $table->string('sheet_name')->nullable(); // Source sheet name
            $table->timestamps();
            
            // Add unique constraint for mobile_no, year, and month
            $table->unique(['mobile_no', 'year', 'month'], 'unique_mobile_year_month');
            
            // Add indexes for better performance
            $table->index(['year', 'month']);
            $table->index('mobile_no');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monthly_bt_sheet_data');
    }
};
