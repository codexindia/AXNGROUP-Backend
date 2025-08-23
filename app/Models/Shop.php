<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shop extends Model
{
    use HasFactory;

    protected $fillable = [
        'agent_id',
        'customer_name',
        'customer_mobile',
        'status',
        'reject_remark',
    ];

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function bankTransfers()
    {
        return $this->hasMany(BankTransfer::class);
    }

    // Get the team leader through the agent's parent relationship
    public function getTeamLeaderAttribute()
    {
        return $this->agent ? $this->agent->parent : null;
    }
}