<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'agent_photo',
        'aadhar_number',
        'pan_number',
        'address',
        'dob',
        'joining_date',
        'id_card_valid_until',
    ];

    protected $casts = [
        'dob' => 'date',
        'joining_date' => 'date',
        'id_card_valid_until' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function bankDetails()
    {
        return $this->hasMany(BankDetail::class, 'user_id', 'user_id');
    }

    /**
     * Get the designation based on user role
     * Agent -> FSE (Field Sales Executive)
     * Leader -> Team Leader
     * Admin -> Admin
     */
    public function getDesignationAttribute()
    {
        if (!$this->user) {
            return null;
        }

        return match($this->user->role) {
            'agent' => 'FSE',
            'leader' => 'Team Leader',
            'admin' => 'Admin',
            default => ucfirst($this->user->role)
        };
    }
}