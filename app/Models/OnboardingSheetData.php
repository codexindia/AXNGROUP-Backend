<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OnboardingSheetData extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'phone', 
        'referral',
        'qr_trx',
        'date'
    ];

    protected $casts = [
        'date' => 'date'
    ];
}
