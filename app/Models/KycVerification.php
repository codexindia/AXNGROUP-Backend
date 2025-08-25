<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class KycVerification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'aadhar_number',
        'aadhar_photo',
        'pan_number',
        'pan_photo',
        'bank_account_number',
        'bank_ifsc_code',
        'bank_name',
        'account_holder_name',
        'passbook_photo',
        'profile_photo',
        'working_city',
        'kyc_status',
        'remark',
        'submitted_at',
        'approved_at',
        'approved_by',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    protected $appends = [
        'aadhar_photo_url',
        'pan_photo_url',
        'passbook_photo_url',
        'profile_photo_url',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // Photo URL accessors
    public function getAadharPhotoUrlAttribute()
    {
        return $this->aadhar_photo ? url('storage/' . $this->aadhar_photo) : null;
    }

    public function getPanPhotoUrlAttribute()
    {
        return $this->pan_photo ? url('storage/' . $this->pan_photo) : null;
    }

    public function getPassbookPhotoUrlAttribute()
    {
        return $this->passbook_photo ? url('storage/' . $this->passbook_photo) : null;
    }

    public function getProfilePhotoUrlAttribute()
    {
        return $this->profile_photo ? url('storage/' . $this->profile_photo) : null;
    }

    // Helper methods
    public function markAsSubmitted()
    {
        $this->update([
            'kyc_status' => 'pending',
            'submitted_at' => Carbon::now(),
        ]);
    }

    public function approve($adminId, $remark = null)
    {
        $this->update([
            'kyc_status' => 'approved',
            'approved_at' => Carbon::now(),
            'approved_by' => $adminId,
            'remark' => $remark,
        ]);
    }

    public function reject($adminId, $remark)
    {
        $this->update([
            'kyc_status' => 'rejected',
            'approved_at' => Carbon::now(),
            'approved_by' => $adminId,
            'remark' => $remark,
        ]);
    }

    // Validation rules
    public static function validationRules()
    {
        return [
            'aadhar_number' => 'required|string|size:12|regex:/^[0-9]{12}$/',
            'aadhar_photo' => 'required|image|mimes:jpeg,png,jpg|max:2048',
            'pan_number' => 'required|string|size:10|regex:/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/',
            'pan_photo' => 'required|image|mimes:jpeg,png,jpg|max:2048',
            'bank_account_number' => 'required|string|min:9|max:20',
            'bank_ifsc_code' => 'required|string|size:11|regex:/^[A-Z]{4}0[A-Z0-9]{6}$/',
            'bank_name' => 'required|string|max:100',
            'account_holder_name' => 'required|string|max:100',
            'passbook_photo' => 'required|image|mimes:jpeg,png,jpg|max:2048',
            'profile_photo' => 'required|image|mimes:jpeg,png,jpg|max:2048',
            'working_city' => 'required|string|max:100',
        ];
    }

    // Update validation rules (for editing existing KYC)
    public static function updateValidationRules($kycId)
    {
        return [
            'aadhar_number' => 'sometimes|string|size:12|regex:/^[0-9]{12}$/',
            'aadhar_photo' => 'sometimes|image|mimes:jpeg,png,jpg|max:2048',
            'pan_number' => 'sometimes|string|size:10|regex:/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/',
            'pan_photo' => 'sometimes|image|mimes:jpeg,png,jpg|max:2048',
            'bank_account_number' => 'sometimes|string|min:9|max:20',
            'bank_ifsc_code' => 'sometimes|string|size:11|regex:/^[A-Z]{4}0[A-Z0-9]{6}$/',
            'bank_name' => 'sometimes|string|max:100',
            'account_holder_name' => 'sometimes|string|max:100',
            'passbook_photo' => 'sometimes|image|mimes:jpeg,png,jpg|max:2048',
            'profile_photo' => 'sometimes|image|mimes:jpeg,png,jpg|max:2048',
            'working_city' => 'sometimes|string|max:100',
        ];
    }
}