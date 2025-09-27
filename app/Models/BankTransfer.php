<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BankTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'agent_id',
        'customer_name',
        'customer_mobile',
        'shop_name',
        'amount',
        'status',
        'amount_change_remark',
        'reject_remark',
    ];
    

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'amount' => 'decimal:2',
    ];

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    // Get the team leader through the agent's parent relationship
    public function getTeamLeaderAttribute()
    {
        return $this->agent ? $this->agent->parent : null;
    }
}