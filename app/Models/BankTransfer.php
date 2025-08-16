<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BankTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'agent_id',
        'team_leader_id',
        'customer_name',
        'customer_mobile',
        'amount',
        'status',
        'amount_change_remark',
        'reject_remark',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function teamLeader()
    {
        return $this->belongsTo(User::class, 'team_leader_id');
    }
}