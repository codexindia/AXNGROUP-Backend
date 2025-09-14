<?php

namespace App\Http\Controllers\Api\Profile;

use App\Http\Controllers\Controller;
use App\Models\UserProfile;
use App\Models\BankDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function updateProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'agent_photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'aadhar_number' => 'nullable|string|size:12',
            'pan_number' => 'nullable|string|size:10',
            'address' => 'nullable|string|max:500',
            'dob' => 'nullable|date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $profile = $user->profile ?: new UserProfile(['user_id' => $user->id]);

        // Handle photo upload
        if ($request->hasFile('agent_photo')) {
            // Delete old photo if exists
            if ($profile->agent_photo) {
                Storage::disk('public')->delete($profile->agent_photo);
            }
            
            $photoPath = $request->file('agent_photo')->store('agent_photos', 'public');
            $profile->agent_photo = $photoPath;
        }

        $profile->fill($request->only(['aadhar_number', 'pan_number', 'address', 'dob']));
        $profile->save();

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => $profile
        ]);
    }

    public function getProfile(Request $request)
    {
        $profile = $request->user()->profile;

        return response()->json([
            'success' => true,
            'data' => $profile
        ]);
    }

    public function addBankDetails(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'account_holder_name' => 'required|string|max:255',
            'bank_name' => 'required|string|max:255',
            'account_number' => 'required|string|max:20',
            'confirm_account_number' => 'required|string|same:account_number',
            'ifsc_code' => 'required|string|size:11'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        $bankDetail = BankDetail::create([
            'user_id' => $request->user()->id,
            'account_holder_name' => $request->account_holder_name,
            'bank_name' => $request->bank_name,
            'account_number' => $request->account_number,
            'confirm_account_number' => $request->confirm_account_number,
            'ifsc_code' => $request->ifsc_code,
            'is_verified' => false
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Bank details added successfully',
            'data' => $bankDetail
        ], 201);
    }

    public function getBankDetails(Request $request)
    {
        $bankDetails = $request->user()->bankDetails;

        return response()->json([
            'success' => true,
            'data' => $bankDetails
        ]);
    }

    public function updateBankDetails(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'account_holder_name' => 'required|string|max:255',
            'bank_name' => 'required|string|max:255',
            'account_number' => 'required|string|max:20',
            'confirm_account_number' => 'required|string|same:account_number',
            'ifsc_code' => 'required|string|size:11'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        $bankDetail = BankDetail::where('id', $id)
                                ->where('user_id', $request->user()->id)
                                ->first();

        if (!$bankDetail) {
            return response()->json([
                'success' => false,
                'message' => 'Bank details not found'
            ], 404);
        }

        $bankDetail->update([
            'account_holder_name' => $request->account_holder_name,
            'bank_name' => $request->bank_name,
            'account_number' => $request->account_number,
            'confirm_account_number' => $request->confirm_account_number,
            'ifsc_code' => $request->ifsc_code,
            'is_verified' => false // Reset verification when updated
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Bank details updated successfully',
            'data' => $bankDetail
        ]);
    }

    public function deleteBankDetails(Request $request, $id)
    {
        $bankDetail = BankDetail::where('id', $id)
                                ->where('user_id', $request->user()->id)
                                ->first();

        if (!$bankDetail) {
            return response()->json([
                'success' => false,
                'message' => 'Bank details not found'
            ], 404);
        }

        $bankDetail->delete();

        return response()->json([
            'success' => true,
            'message' => 'Bank details deleted successfully'
        ]);
    }
}