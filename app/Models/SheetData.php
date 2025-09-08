<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SheetData extends Model
{
    use HasFactory;

    protected $table = 'sheet_data';

    protected $fillable = [
        'date',
        'cus_no',
        'actual_bt_tide',
        'sheet_name'
    ];

    protected $casts = [
        'date' => 'date',
        'actual_bt_tide' => 'decimal:2'
    ];

    /**
     * Scope to filter by date
     */
    public function scopeByDate($query, $date)
    {
        return $query->where('date', $date);
    }

    /**
     * Scope to filter by customer number
     */
    public function scopeByCusNo($query, $cusNo)
    {
        return $query->where('cus_no', $cusNo);
    }
}
