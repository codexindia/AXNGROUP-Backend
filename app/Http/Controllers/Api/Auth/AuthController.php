<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Api\AdminController;
use Carbon\Carbon;

class AuthController extends Controller
{
    public function loginAdmin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mobile' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('mobile', $request->mobile)
                   ->where('role', 'admin')
                   ->where('is_blocked', false)
                   ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        // Clean user data
        $userData = $user->toArray();
        unset($userData['email_verified_at'], $userData['deleted_at'], $userData['referral_code']);

        return response()->json([
            'success' => true,
            'message' => 'Admin login successful',
            'data' => [
                'user' => $userData,
                'token' => $token,
                'token_type' => 'Bearer'
            ]
        ]);
    }

    public function loginLeader(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mobile' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('mobile', $request->mobile)
                   ->where('role', 'leader')
                   ->where('is_blocked', false)
                   ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        // Load wallet and clean user data
        $user->load('wallet');
        $userData = $user->toArray();
        $userData['wallet_balance'] = $user->wallet ? $user->wallet->balance : '0.00';
        unset($userData['wallet'], $userData['email_verified_at'], $userData['deleted_at']);

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => $userData,
                'token' => $token,
                'token_type' => 'Bearer'
            ]
        ]);
    }

    public function loginAgent(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mobile' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('mobile', $request->mobile)
                   ->where('role', 'agent')
                   ->where('is_blocked', false)
                   ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        // Load wallet and clean user data
        $user->load('wallet');
        $userData = $user->toArray();
        $userData['wallet_balance'] = $user->wallet ? $user->wallet->balance : '0.00';
        unset($userData['wallet'], $userData['email_verified_at'], $userData['deleted_at']);

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => $userData,
                'token' => $token,
                'token_type' => 'Bearer'
            ]
        ]);
    }

    public function registerLeader(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'mobile' => 'required|string|unique:users,mobile',
            'email' => 'nullable|email|unique:users,email',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if the authenticated user is an admin
        if (auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Only admins can register leaders'
            ], 403);
        }

        // Generate unique_id for leader
        $lastUser = User::orderBy('id', 'desc')->first();
        $nextNumber = $lastUser ? (intval(substr($lastUser->unique_id, 3)) + 1) : 1;
        $unique_id = 'AXN' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);

        $user = User::create([
            'unique_id' => $unique_id,
            'name' => $request->name,
            'mobile' => $request->mobile,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'leader',
            'parent_id' => auth()->user()->id, // Set admin as parent of leader
            'is_blocked' => false,
        ]);

        // Load wallet and format response
        $user->load('wallet');
        $userData = $user->toArray();
        $userData['wallet_balance'] = $user->wallet ? $user->wallet->balance : '0.00';
        unset($userData['wallet'], $userData['email_verified_at'], $userData['deleted_at']);

        return response()->json([
            'success' => true,
            'message' => 'Leader registered successfully',
            'data' => $userData
        ], 201);
    }

    public function registerAgent(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'mobile' => 'required|string|unique:users,mobile',
            'email' => 'nullable|email|unique:users,email',
            'password' => 'required|string|min:8',
            'referral_code' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if the authenticated user is a leader
        if (auth()->user()->role !== 'leader') {
            return response()->json([
                'success' => false,
                'message' => 'Only leaders can register agents'
            ], 403);
        }

        // Generate unique_id
        $lastUser = User::orderBy('id', 'desc')->first();
        $nextNumber = $lastUser ? (intval(substr($lastUser->unique_id, 3)) + 1) : 1;
        $unique_id = 'AXN' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);

        $user = User::create([
            'unique_id' => $unique_id,
            'name' => $request->name,
            'mobile' => $request->mobile,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'agent',
            'parent_id' => auth()->user()->id, // Set leader as parent of agent
            'referral_code' => $request->referral_code,
            'is_blocked' => false,
        ]);

        // Load wallet and format response
        $user->load('wallet');
        $userData = $user->toArray();
        $userData['wallet_balance'] = $user->wallet ? $user->wallet->balance : '0.00';
        unset($userData['wallet'], $userData['email_verified_at'], $userData['deleted_at']);

        return response()->json([
            'success' => true,
            'message' => 'Agent registered successfully',
            'data' => $userData
        ], 201);
    }

    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
    }

    public function profile(Request $request)
    {
        $user = User::find($request->user()->id);
        
        // Load relationships based on role
        if (in_array($user->role, ['agent', 'leader'])) {
            $user->load(['wallet','profile']);
             $this->addIdCardInfo($user);
            // Merge wallet balance into user object and remove wallet object
            $userData = $user->toArray();
            $userData['wallet_balance'] = $user->wallet ? $user->wallet->balance : '0.00';
            unset($userData['wallet']);
            
            // Remove unnecessary keys
            unset($userData['email_verified_at'], $userData['deleted_at']);
            
        } else {
            $user->load(['profile']);
             $this->addIdCardInfo($user);
            $userData = $user->toArray();
            
            // Remove unnecessary keys for admin
            unset($userData['email_verified_at'], $userData['deleted_at'], $userData['referral_code']);
        }
       
       

        return response()->json([
            'success' => true,
            'data' => $userData
        ]);
    }
    public function getPrimaryDomain()
{
    $host = request()->getHost(); // e.g. app.example.com

    // Split the host by dots
    $parts = explode('.', $host);

    // Handle scenarios like sub.sub.example.com or example.co.in
    if (count($parts) >= 2) {
        // For simple domains like example.com
        $primaryDomain = implode('.', array_slice($parts, -2));
    }else {
        // Fallback to the original host if it doesn't have at least two parts
        $primaryDomain = $host;
    }

    // You can enhance this to handle TLDs like .co.in
    // Add special logic if needed

    return $primaryDomain; // example.com
}
   private function addIdCardInfo($user)
    {
        $idCardStatus = 'not_issued';
        $idCardDetails = null;

        if ($user->profile) {
            $validUntil = $user->profile->id_card_validity 
                ? Carbon::parse($user->profile->id_card_validity) 
                : null;

            if ($validUntil) {
                if ($validUntil->isFuture()) {
                    $idCardStatus = 'active';
                } else {
                    $idCardStatus = 'expired';
                }

                $idCardDetails = [
                    'unique_id' => $user->unique_id,
                    'verify_url' => 'https://'.$this->getPrimaryDomain() . '/verify/check-id.html?id=' . $user->unique_id,
                    'profile_photo' => $user->profile->user_photo ,
                    'issued_date' => $user->profile->issued_date
                        ? url('storage/' . $user->profile->user_photo) 
                        : null,
                    'blood_group' => $user->profile->blood_group,
                    'valid_until' => $validUntil->format('Y-m-d'),
                    'days_remaining' => $validUntil->isFuture() 
                        ? $validUntil->diffInDays(now()) 
                        : 0
                ];
            }
        }

        $user->id_card_status = $idCardStatus; // 'not_issued', 'active', 'expired'
        $user->id_card = $idCardDetails;

        // Remove profile relation from response to avoid duplication
        unset($user->profile);

        return $user;
    }
public function verifyIdCard($uniqueId): JsonResponse
{
    $user = User::where('unique_id', $uniqueId)
                ->with('profile')
                ->first();

    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'ID card not found',
            'error' => 'Invalid ID card number'
        ], 404);
    }

    // Check if user has a profile with ID card validity
    if (!$user->profile || !$user->profile->id_card_validity) {
        return response()->json([
            'success' => false,
            'message' => 'ID card not issued',
            'error' => 'This ID card has not been issued yet'
        ], 404);
    }

    $validUntil = Carbon::parse($user->profile->id_card_validity);
    $isExpired = $validUntil->isPast();

    // Add ID card status
    $idCardStatus = $isExpired ? 'expired' : 'active';

    return response()->json([
        'success' => true,
        'message' => 'ID card verified successfully',
        'data' => [
            'unique_id' => $user->unique_id,
            'name' => $user->name,
            'email' => $user->email,
            'mobile' => $user->mobile,
            'role' => ucfirst($user->role),
            'profile_photo' => $user->profile->user_photo 
                ? url('storage/' . $user->profile->user_photo) 
                : null,
            'blood_group' => $user->profile->blood_group,
            'valid_until' => $validUntil->format('d M Y'),
            'issued_date' => $user->profile->created_at->format('d M Y'),
            'status' => $idCardStatus,
            'is_expired' => $isExpired,
            'days_remaining' => $isExpired ? 0 : $validUntil->diffInDays(now()),
            'is_blocked' => $user->is_blocked
        ]
    ]);
}
}