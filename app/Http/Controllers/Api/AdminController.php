<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class AdminController extends Controller
{
    /**
     * Toggle user block status (Block/Unblock)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function toggleUserBlock(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id'
        ]);

        $userId = $request->user_id;
        
        // Prevent admin from blocking themselves
        if (auth()->id() == $userId) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot modify your own block status'
            ], 400);
        }

        $user = User::find($userId);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        // Prevent blocking other admins
        if ($user->role === 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot modify admin user block status'
            ], 400);
        }

        $newStatus = !$user->is_blocked;
        $user->update(['is_blocked' => $newStatus]);

        $action = $newStatus ? 'blocked' : 'unblocked';

        return response()->json([
            'success' => true,
            'message' => "User has been {$action} successfully",
            'data' => [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_blocked' => $user->is_blocked,
                'action' => $action
            ]
        ]);
    }

    /**
     * Get users list with filters (Admin only)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getUsersList(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:agents,leaders,all',
            'search' => 'nullable|string|max:255',
            'is_blocked' => 'nullable|boolean',
            'leader_id' => 'nullable|exists:users,id',
            'per_page' => 'nullable|integer|min:1|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $query = User::query();

        // Filter by type
        switch ($request->type) {
            case 'agents':
                $query->where('role', 'agent');
                break;
            case 'leaders':
                $query->where('role', 'leader');
                break;
            case 'all':
                // No role filter for all users
                break;
        }

        // Filter agents by leader if leader_id provided
        if ($request->filled('leader_id') && $request->type === 'agents') {
            $query->where('parent_id', $request->leader_id);
        }

        // Apply search filter
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                  ->orWhere('email', 'like', "%{$searchTerm}%")
                  ->orWhere('mobile', 'like', "%{$searchTerm}%")
                  ->orWhere('unique_id', 'like', "%{$searchTerm}%");
            });
        }

        // Apply blocked status filter
        if ($request->has('is_blocked')) {
            $query->where('is_blocked', $request->boolean('is_blocked'));
        }

        // Select fields and relationships - Include profile for ID card info
        $query->select(['id', 'unique_id', 'name', 'email', 'mobile', 'role', 'parent_id', 'is_blocked', 'created_at'])
              ->with(['parent:id,name,unique_id', 'profile:id,user_id,user_photo,id_card_validity,blood_group']);

        // Add counts for leaders and agents
        if ($request->type === 'leaders') {
            $query->withCount('agents'); // Simple agent count for leaders
        }

        $users = $query->orderBy('created_at', 'desc')
                      ->paginate($request->get('per_page', 20));

        // Add performance counts and ID card status after pagination
        if ($request->type === 'leaders') {
            // Add team performance counts for leaders
            $users->getCollection()->transform(function ($leader) {
                // Get all agent IDs under this leader
                $agentIds = User::where('parent_id', $leader->id)
                               ->where('role', 'agent')
                               ->pluck('id')
                               ->toArray();

                if (!empty($agentIds)) {
                    // Count bank transfers for today
                    $dailyBankTransfers = \App\Models\BankTransfer::whereIn('agent_id', $agentIds)
                        ->whereDate('created_at', today())
                        ->sum('amount');

                    // Count bank transfers for this month
                    $monthlyBankTransfers = \App\Models\BankTransfer::whereIn('agent_id', $agentIds)
                        ->whereMonth('created_at', now()->month)
                        ->whereYear('created_at', now()->year)
                        ->sum('amount');

                    // Count shop onboardings for today
                    $dailyShopOnboardings = \App\Models\Shop::whereIn('agent_id', $agentIds)
                        ->whereDate('created_at', today())
                        ->count();

                    // Count shop onboardings for this month
                    $monthlyShopOnboardings = \App\Models\Shop::whereIn('agent_id', $agentIds)
                        ->whereMonth('created_at', now()->month)
                        ->whereYear('created_at', now()->year)
                        ->count();

                    // Add counts to leader object
                    $leader->daily_bank_transfers_count = $dailyBankTransfers;
                    $leader->monthly_bank_transfers_count = $monthlyBankTransfers;
                    $leader->daily_shop_onboarding_count = $dailyShopOnboardings;
                    $leader->monthly_shop_onboarding_count = $monthlyShopOnboardings;
                } else {
                    // No agents under this leader
                    $leader->daily_bank_transfers_count = 0;
                    $leader->monthly_bank_transfers_count = 0;
                    $leader->daily_shop_onboarding_count = 0;
                    $leader->monthly_shop_onboarding_count = 0;
                }

                // Add ID card information
                $this->addIdCardInfo($leader);

                return $leader;
            });
        } elseif ($request->type === 'agents') {
            // Add individual performance counts for agents
            $users->getCollection()->transform(function ($agent) {
                // Count agent's own bank transfers for today
                $dailyBankTransfers = \App\Models\BankTransfer::where('agent_id', $agent->id)
                    ->whereDate('created_at', today())
                    ->count();

                // Count agent's own bank transfers for this month
                $monthlyBankTransfers = \App\Models\BankTransfer::where('agent_id', $agent->id)
                    ->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->sum('amount');

                // Count agent's own total bank transfers
                $totalBankTransfers = \App\Models\BankTransfer::where('agent_id', $agent->id)
                    ->sum('amount');

                // Count agent's own shop onboardings for today
                $dailyShopOnboardings = \App\Models\Shop::where('agent_id', $agent->id)
                    ->whereDate('created_at', today())
                    ->count();

                // Count agent's own shop onboardings for this month
                $monthlyShopOnboardings = \App\Models\Shop::where('agent_id', $agent->id)
                    ->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->count();

                // Count agent's own total shop onboardings
                $totalShopOnboardings = \App\Models\Shop::where('agent_id', $agent->id)
                    ->count();

                // Add counts to agent object
                $agent->daily_bank_transfers_count = $dailyBankTransfers;
                $agent->monthly_bank_transfers_count = $monthlyBankTransfers;
                $agent->total_bank_transfers_count = $totalBankTransfers;
                $agent->daily_shop_onboarding_count = $dailyShopOnboardings;
                $agent->monthly_shop_onboarding_count = $monthlyShopOnboardings;
                $agent->total_shop_onboarding_count = $totalShopOnboardings;

                // Add ID card information
                $this->addIdCardInfo($agent);

                return $agent;
            });
        } else {
            // For 'all' type, just add ID card info
            $users->getCollection()->transform(function ($user) {
                $this->addIdCardInfo($user);
                return $user;
            });
        }

        // Get statistics based on type
        $stats = [];
        switch ($request->type) {
            case 'agents':
                $stats = [
                    'total_agents' => User::where('role', 'agent')->count(),
                    'blocked_agents' => User::where('role', 'agent')->where('is_blocked', true)->count(),
                    'active_agents' => User::where('role', 'agent')->where('is_blocked', false)->count(),
                ];
                break;
            case 'leaders':
                $stats = [
                    'total_leaders' => User::where('role', 'leader')->count(),
                    'blocked_leaders' => User::where('role', 'leader')->where('is_blocked', true)->count(),
                    'active_leaders' => User::where('role', 'leader')->where('is_blocked', false)->count(),
                ];
                break;
            case 'all':
                $stats = [
                    'total_users' => User::count(),
                    'total_agents' => User::where('role', 'agent')->count(),
                    'total_leaders' => User::where('role', 'leader')->count(),
                    'total_admins' => User::where('role', 'admin')->count(),
                    'blocked_users' => User::where('is_blocked', true)->count(),
                ];
                break;
        }

        return response()->json([
            'success' => true,
            'data' => $users,
            'statistics' => $stats
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

    /**
     * Helper method to add ID card information to user object
     */
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
                    'verify_url' => 'https://'.$this->getPrimaryDomain() . '/verify/check-id.html/' . $user->unique_id,
                    'profile_photo' => $user->profile->user_photo 
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

    /**
     * Issue ID Card to User
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function issueIdCard(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'profile_photo' => 'nullable|image|mimes:jpeg,jpg,png|max:2048',
            'valid_until' => 'required|date|after:today',
            'blood_group' => 'nullable|string|in:A+,A-,B+,B-,AB+,AB-,O+,O-'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::find($request->user_id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        // Check if profile exists
        $profile = UserProfile::where('user_id', $user->id)->first();

        $profilePhotoPath = null;

        // Handle profile photo upload
        if ($request->hasFile('profile_photo')) {
            // Delete old photo if exists
            if ($profile && $profile->user_photo) {
                Storage::disk('public')->delete($profile->user_photo);
            }

            $file = $request->file('profile_photo');
            $fileName = 'profile_' . $user->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $profilePhotoPath = $file->storeAs('profiles', $fileName, 'public');
        } elseif ($profile && $profile->user_photo) {
            // Keep existing photo if no new photo uploaded
            $profilePhotoPath = $profile->user_photo;
        }

        // Create or update profile
        $profileData = [
            'user_id' => $user->id,
            'id_card_validity' => $request->valid_until,
            'blood_group' => $request->blood_group
        ];

        if ($profilePhotoPath) {
            $profileData['user_photo'] = $profilePhotoPath;
        }

        if ($profile) {
            $profile->update($profileData);
        } else {
            $profile = UserProfile::create($profileData);
        }

        // Get updated user with profile
        $user->load('profile');
        $this->addIdCardInfo($user);

        return response()->json([
            'success' => true,
            'message' => 'ID card issued successfully',
            'data' => [
                'user_id' => $user->id,
                'name' => $user->name,
                'unique_id' => $user->unique_id,
                'id_card_status' => $user->id_card_status,
               // 'id_card' => $user->id_card
            ]
        ]);
    }

    /**
     * Get ID Card Details for a User
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getIdCardDetails(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::with('profile')->find($request->user_id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        $this->addIdCardInfo($user);

        return response()->json([
            'success' => true,
            'data' => [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'mobile' => $user->mobile,
                'unique_id' => $user->unique_id,
                'role' => $user->role,
                'id_card_status' => $user->id_card_status,
                'id_card' => $user->id_card
            ]
        ]);
    }

    /**
     * Renew ID Card
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function renewIdCard(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'valid_until' => 'required|date|after:today'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::find($request->user_id);
        $profile = UserProfile::where('user_id', $user->id)->first();

        if (!$profile) {
            return response()->json([
                'success' => false,
                'message' => 'User does not have an ID card. Please issue a new card first.'
            ], 404);
        }

        $profile->update([
            'id_card_validity' => $request->valid_until
        ]);

        $user->load('profile');
        $this->addIdCardInfo($user);

        return response()->json([
            'success' => true,
            'message' => 'ID card renewed successfully',
            'data' => [
                'user_id' => $user->id,
                'name' => $user->name,
                'unique_id' => $user->unique_id,
                'id_card_status' => $user->id_card_status,
                'id_card' => $user->id_card
            ]
        ]);
    }
}
