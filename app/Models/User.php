<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'unique_id',
        'name',
        'mobile',
        'email',
        'password',
        'role',
        'referral_code',
        'is_blocked',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_blocked' => 'boolean',
    ];

    // Wallet relationship - only agents and leaders have wallets
    public function wallet()
    {
        return $this->hasOne(Wallet::class);
    }

    // Profile relationship - all users can have profiles
    public function profile()
    {
        return $this->hasOne(UserProfile::class);
    }

    // Agent's shops
    public function shops()
    {
        return $this->hasMany(Shop::class, 'agent_id');
    }

    // Leader's assigned shops
    public function assignedShops()
    {
        return $this->hasMany(Shop::class, 'team_leader_id');
    }

    // Agent's bank transfers
    public function bankTransfers()
    {
        return $this->hasMany(BankTransfer::class, 'agent_id');
    }

    // Leader's assigned bank transfers
    public function assignedBankTransfers()
    {
        return $this->hasMany(BankTransfer::class, 'team_leader_id');
    }

    // User's withdrawals
    public function withdrawals()
    {
        return $this->hasMany(Withdrawal::class);
    }

    // Boot method to create wallet for agents and leaders
    protected static function booted()
    {
        static::created(function ($user) {
            if (in_array($user->role, ['agent', 'leader'])) {
                $user->wallet()->create(['balance' => 0]);
            }
        });
    }
}
