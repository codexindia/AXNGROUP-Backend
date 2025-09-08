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
        Schema::create('sheet_data', function (Blueprint $table) {
            $table->id();
            $table->date('date'); // Date column from sheet
            $table->string('cus_no'); // Customer number (mobile number)
            $table->decimal('actual_bt_tide', 10, 2)->nullable(); // ACTUAL BT_TIDE column
            $table->string('sheet_name')->nullable(); // Source sheet name
            $table->timestamps();
            
            // Add indexes for better performance
            $table->index(['date', 'cus_no']);
            $table->index('date');
            $table->index('cus_no');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sheet_data');
    }
};
