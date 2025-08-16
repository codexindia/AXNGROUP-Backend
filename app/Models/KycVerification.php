<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KycVerification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'kyc_status',
        'remark',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}