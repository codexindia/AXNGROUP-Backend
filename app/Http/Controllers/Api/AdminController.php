<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

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

        // Select fields and relationships
        $query->select(['id', 'unique_id', 'name', 'email', 'mobile', 'role', 'parent_id', 'is_blocked', 'created_at'])
              ->with(['parent:id,name,unique_id']);

        // Add counts for leaders and agents
        if ($request->type === 'leaders') {
            $query->withCount('agents'); // Simple agent count for leaders
        }

        $users = $query->orderBy('created_at', 'desc')
                      ->paginate($request->get('per_page', 20));

        // Add performance counts after pagination
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

                return $agent;
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

    /**
     * Get complete agent details for ID card generation
     *
     * @param int $agentId
     * @return JsonResponse
     */
    public function getAgentDetails($agentId): JsonResponse
    {
        $agent = User::with([
            'profile',
            'kycVerification',
            'bankDetails',
            'wallet',
            'parent:id,name,unique_id,role'
        ])->find($agentId);

        if (!$agent) {
            return response()->json([
                'success' => false,
                'message' => 'Agent not found'
            ], 404);
        }

        // Verify user is actually an agent or leader (for ID card)
        if (!in_array($agent->role, ['agent', 'leader'])) {
            return response()->json([
                'success' => false,
                'message' => 'User is not an agent or leader'
            ], 400);
        }

        // Get profile photo (Priority: KYC profile_photo > user_profiles agent_photo)
        $profilePhotoUrl = null;
        if ($agent->kycVerification && $agent->kycVerification->profile_photo) {
            $profilePhotoUrl = Storage::disk('public')->url($agent->kycVerification->profile_photo);
        } elseif ($agent->profile && $agent->profile->agent_photo) {
            $profilePhotoUrl = Storage::disk('public')->url($agent->profile->agent_photo);
        }

        // Get designation based on role
        $designation = match($agent->role) {
            'agent' => 'FSE',
            'leader' => 'Team Leader',
            'admin' => 'Admin',
            default => ucfirst($agent->role)
        };

        // Get KYC status
        $kycStatus = $agent->kycVerification ? $agent->kycVerification->kyc_status : 'not_submitted';
        $isKycVerified = $kycStatus === 'approved';

        // Get account status
        $isActive = !$agent->is_blocked;

        // Prepare response
        $response = [
            'id' => $agent->id,
            'unique_id' => $agent->unique_id,
            'name' => $agent->name,
            'mobile' => $agent->mobile,
            'email' => $agent->email,
            'role' => $agent->role,
            'designation' => $designation,
            'is_active' => $isActive,
            'is_blocked' => $agent->is_blocked,
            'profile_photo_url' => $profilePhotoUrl,
            'kyc_status' => $kycStatus,
            'is_kyc_verified' => $isKycVerified,
            'wallet_balance' => $agent->wallet ? $agent->wallet->balance : '0.00',
            'created_at' => $agent->created_at,
            
            // Profile information
            'profile' => $agent->profile ? [
                'aadhar_number' => $agent->profile->aadhar_number,
                'pan_number' => $agent->profile->pan_number,
                'address' => $agent->profile->address,
                'dob' => $agent->profile->dob?->format('Y-m-d'),
                'joining_date' => $agent->profile->joining_date?->format('Y-m-d'),
                'id_card_valid_until' => $agent->profile->id_card_valid_until?->format('Y-m-d'),
            ] : null,
            
            // KYC information
            'kyc' => $agent->kycVerification ? [
                'working_city' => $agent->kycVerification->working_city,
                'kyc_status' => $agent->kycVerification->kyc_status,
                'submitted_at' => $agent->kycVerification->submitted_at?->format('Y-m-d H:i:s'),
                'approved_at' => $agent->kycVerification->approved_at?->format('Y-m-d H:i:s'),
            ] : null,
            
            // Parent (Leader/Admin) information
            'parent' => $agent->parent ? [
                'id' => $agent->parent->id,
                'name' => $agent->parent->name,
                'unique_id' => $agent->parent->unique_id,
                'role' => $agent->parent->role,
            ] : null,
            
            // Bank details
            'bank_details' => $agent->bankDetails->map(function ($bank) {
                return [
                    'id' => $bank->id,
                    'account_holder_name' => $bank->account_holder_name,
                    'bank_name' => $bank->bank_name,
                    'account_number' => $bank->account_number,
                    'ifsc_code' => $bank->ifsc_code,
                ];
            }),
        ];

        return response()->json([
            'success' => true,
            'data' => $response
        ]);
    }

    /**
     * Update agent's ID card information (photo, joining date, valid until)
     * Admin only - flexible update (can update one or all fields)
     *
     * @param Request $request
     * @param int $agentId
     * @return JsonResponse
     */
    public function updateAgentIdCardInfo(Request $request, $agentId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'agent_photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'joining_date' => 'nullable|date|before_or_equal:today',
            'id_card_valid_until' => 'nullable|date|after:today',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $agent = User::with('profile')->find($agentId);

        if (!$agent) {
            return response()->json([
                'success' => false,
                'message' => 'Agent not found'
            ], 404);
        }

        // Verify user is agent or leader
        if (!in_array($agent->role, ['agent', 'leader'])) {
            return response()->json([
                'success' => false,
                'message' => 'User is not an agent or leader'
            ], 400);
        }

        // Get or create profile
        $profile = $agent->profile;
        if (!$profile) {
            $profile = $agent->profile()->create([
                'user_id' => $agent->id
            ]);
        }

        $updated = false;

        // Handle photo upload
        if ($request->hasFile('agent_photo')) {
            // Delete old photo if exists
            if ($profile->agent_photo) {
                Storage::disk('public')->delete($profile->agent_photo);
            }
            
            $photoPath = $request->file('agent_photo')->store('agent_photos', 'public');
            $profile->agent_photo = $photoPath;
            $updated = true;
        }

        // Update joining date if provided
        if ($request->has('joining_date')) {
            $profile->joining_date = $request->joining_date;
            $updated = true;
        }

        // Update ID card expiry if provided
        if ($request->has('id_card_valid_until')) {
            $profile->id_card_valid_until = $request->id_card_valid_until;
            $updated = true;
        }

        if ($updated) {
            $profile->save();
        }

        // Get updated photo URL
        $profilePhotoUrl = $profile->agent_photo 
            ? Storage::disk('public')->url($profile->agent_photo) 
            : null;

        return response()->json([
            'success' => true,
            'message' => 'Agent ID card information updated successfully',
            'data' => [
                'agent_id' => $agent->id,
                'name' => $agent->name,
                'unique_id' => $agent->unique_id,
                'profile_photo_url' => $profilePhotoUrl,
                'joining_date' => $profile->joining_date?->format('Y-m-d'),
                'id_card_valid_until' => $profile->id_card_valid_until?->format('Y-m-d'),
            ]
        ]);
    }
}
