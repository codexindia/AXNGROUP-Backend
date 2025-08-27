<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RewardPass extends Model
{
    use HasFactory;

    protected $fillable = [
        'agent_id',
        'customer_name',
        'customer_mobile',
        'status',
        'reject_remark',
    ];

    // Relationships
    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    // Validation rules
    public static function validationRules()
    {
        return [
            'customer_name' => 'required|string|max:255',
            'customer_mobile' => 'required|string|max:15|regex:/^[0-9]{10,15}$/',
        ];
    }

    // Scope for agent's reward passes
    public function scopeForAgent($query, $agentId)
    {
        return $query->where('agent_id', $agentId);
    }

    // Scope for status filtering
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }
}
