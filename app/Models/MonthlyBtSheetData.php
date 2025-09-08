<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MonthlyBtSheetData extends Model
{
    use HasFactory;

    protected $table = 'monthly_bt_sheet_data';

    protected $fillable = [
        'cus_name',
        'mobile_no',
        'total_bank_transfer',
        'year',
        'month',
        'sheet_name'
    ];

    protected $casts = [
        'total_bank_transfer' => 'decimal:2',
        'year' => 'integer',
        'month' => 'integer'
    ];

    /**
     * Scope to filter by year and month
     */
    public function scopeByYearMonth($query, $year, $month)
    {
        return $query->where('year', $year)->where('month', $month);
    }

    /**
     * Scope to filter by mobile number
     */
    public function scopeByMobile($query, $mobileNo)
    {
        return $query->where('mobile_no', $mobileNo);
    }
}
