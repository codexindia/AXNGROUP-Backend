<?php

namespace App\Http\Controllers\Api\RewardPass;

use App\Http\Controllers\Controller;
use App\Models\RewardPass;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RewardPassController extends Controller
{
    /**
     * Create reward pass (Agent only)
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), RewardPass::validationRules());

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Only agents can create reward passes
        if ($request->user()->role !== 'agent') {
            return response()->json([
                'success' => false,
                'message' => 'Only agents can create reward passes'
            ], 403);
        }

        $rewardPass = RewardPass::create([
            'agent_id' => $request->user()->id,
            'customer_name' => $request->customer_name,
            'customer_mobile' => $request->customer_mobile,
            'status' => 'pending'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Reward pass created successfully',
            'data' => $rewardPass->load(['agent:id,name'])
        ], 201);
    }

    /**
     * Get agent's reward passes
     */
    public function getByAgent(Request $request)
    {
        $rewardPasses = RewardPass::where('agent_id', $request->user()->id)
                                 ->orderBy('created_at', 'desc')
                                 ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $rewardPasses
        ]);
    }

    /**
     * Get leader's agents' reward passes
     */
    public function getByLeader(Request $request)
    {
        if ($request->user()->role !== 'leader') {
            return response()->json([
                'success' => false,
                'message' => 'Only leaders can view their agents reward passes'
            ], 403);
        }

        // Get all agents under this leader first
        $agentIds = $request->user()->agents()->pluck('id');

        $rewardPasses = RewardPass::whereIn('agent_id', $agentIds)
                                 ->with(['agent:id,name'])
                                 ->orderBy('created_at', 'desc')
                                 ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $rewardPasses
        ]);
    }

    /**
     * Show specific reward pass
     */
    public function show($id)
    {
        $rewardPass = RewardPass::with(['agent.parent'])->find($id);

        if (!$rewardPass) {
            return response()->json([
                'success' => false,
                'message' => 'Reward pass not found'
            ], 404);
        }

        // Check if user has permission to view this reward pass
        $user = auth()->user();
        $isAgentOwner = $rewardPass->agent_id === $user->id;
        $isTeamLeader = $user->role === 'leader' && $rewardPass->agent->parent_id === $user->id;
        $isAdmin = in_array($user->role, ['admin']);
        
        if (!$isAgentOwner && !$isTeamLeader && !$isAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view this reward pass'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $rewardPass
        ]);
    }

    /**
     * Admin: Get all reward passes with filters
     */
    public function getAllRewardPasses(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Only admins can view all reward passes'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'nullable|in:pending,approved,rejected',
            'agent_id' => 'nullable|exists:users,id',
            'leader_id' => 'nullable|exists:users,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'search' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $query = RewardPass::with(['agent.parent']);

        // Apply filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('agent_id')) {
            $query->where('agent_id', $request->agent_id);
        }

        if ($request->filled('leader_id')) {
            // Filter reward passes by leader - get agents under this leader first
            $agentIds = User::where('parent_id', $request->leader_id)->pluck('id');
            $query->whereIn('agent_id', $agentIds);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                  ->orWhere('customer_mobile', 'like', "%{$search}%")
                  ->orWhereHas('agent', function ($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('agent.parent', function ($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $perPage = $request->get('per_page', 20);
        $rewardPasses = $query->orderBy('created_at', 'desc')->paginate($perPage);

        // Get statistics
        $stats = [
            'total' => RewardPass::count(),
            'pending' => RewardPass::where('status', 'pending')->count(),
            'approved' => RewardPass::where('status', 'approved')->count(),
            'rejected' => RewardPass::where('status', 'rejected')->count(),
            'today' => RewardPass::whereDate('created_at', Carbon::today())->count(),
            'this_month' => RewardPass::whereMonth('created_at', Carbon::now()->month)->count()
        ];

        return response()->json([
            'success' => true,
            'data' => $rewardPasses,
            'statistics' => $stats
        ]);
    }

    /**
     * Admin approval for reward pass
     */
    public function adminApproval(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:approved,rejected',
            'admin_remark' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Only admin can do approval
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Only admin can approve/reject reward passes'
            ], 403);
        }

        $rewardPass = RewardPass::find($id);

        if (!$rewardPass) {
            return response()->json([
                'success' => false,
                'message' => 'Reward pass not found'
            ], 404);
        }

        // Reward pass must be pending
        if ($rewardPass->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Reward pass has already been processed'
            ], 400);
        }

        $rewardPass->update([
            'status' => $request->status,
            'reject_remark' => $request->status === 'rejected' ? $request->admin_remark : null
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Reward pass ' . $request->status . ' successfully',
            'data' => $rewardPass->load(['agent.parent'])
        ]);
    }

    /**
     * Get pending reward passes for admin approval
     */
    public function getPendingForAdmin()
    {
        $rewardPasses = RewardPass::where('status', 'pending')
                                 ->with(['agent.parent'])
                                 ->orderBy('created_at', 'asc')
                                 ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $rewardPasses
        ]);
    }
}
