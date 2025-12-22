<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HierarchyController extends Controller
{
    /**
     * Get all leaders under an admin
     */
    public function getLeadersUnderAdmin(Request $request): JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Only admins can access this endpoint'
            ], 403);
        }

        $today = now()->format('Y-m-d');
        $startOfMonth = now()->startOfMonth()->format('Y-m-d');
        $endOfMonth = now()->endOfMonth()->format('Y-m-d');

        $leaders = $request->user()->leaders()->paginate(10);
        $leaderIds = $leaders->pluck('id')->toArray();

        // Get agent counts per leader
        $agentCounts = DB::table('users')
            ->select('parent_id', DB::raw('COUNT(*) as total_agents'))
            ->whereIn('parent_id', $leaderIds)
            ->where('role', 'agent')
            ->groupBy('parent_id')
            ->pluck('total_agents', 'parent_id');

        // Get daily shop counts per leader
        $dailyShops = DB::table('shops')
            ->join('users', 'shops.agent_id', '=', 'users.id')
            ->select('users.parent_id', DB::raw('COUNT(*) as count'))
            ->whereIn('users.parent_id', $leaderIds)
            ->where('shops.status', 'approved')
            ->whereDate('shops.created_at', $today)
            ->groupBy('users.parent_id')
            ->pluck('count', 'parent_id');

        // Get monthly shop counts per leader
        $monthlyShops = DB::table('shops')
            ->join('users', 'shops.agent_id', '=', 'users.id')
            ->select('users.parent_id', DB::raw('COUNT(*) as count'))
            ->whereIn('users.parent_id', $leaderIds)
            ->where('shops.status', 'approved')
            ->whereBetween('shops.created_at', [$startOfMonth, $endOfMonth])
            ->groupBy('users.parent_id')
            ->pluck('count', 'parent_id');

        // Get daily reward passes per leader
        $dailyRewardPasses = DB::table('reward_passes')
            ->join('users', 'reward_passes.agent_id', '=', 'users.id')
            ->select('users.parent_id', DB::raw('COUNT(*) as count'))
            ->whereIn('users.parent_id', $leaderIds)
            ->where('reward_passes.status', 'approved')
            ->whereDate('reward_passes.created_at', $today)
            ->groupBy('users.parent_id')
            ->pluck('count', 'parent_id');

        // Get monthly reward passes per leader
        $monthlyRewardPasses = DB::table('reward_passes')
            ->join('users', 'reward_passes.agent_id', '=', 'users.id')
            ->select('users.parent_id', DB::raw('COUNT(*) as count'))
            ->whereIn('users.parent_id', $leaderIds)
            ->where('reward_passes.status', 'approved')
            ->whereBetween('reward_passes.created_at', [$startOfMonth, $endOfMonth])
            ->groupBy('users.parent_id')
            ->pluck('count', 'parent_id');

        // Get daily bank transfer amounts per leader
        $dailyBankTransfers = DB::table('bank_transfers')
            ->join('users', 'bank_transfers.agent_id', '=', 'users.id')
            ->select('users.parent_id', DB::raw('SUM(bank_transfers.amount) as total'))
            ->whereIn('users.parent_id', $leaderIds)
            ->where('bank_transfers.status', 'approved')
            ->whereDate('bank_transfers.created_at', $today)
            ->groupBy('users.parent_id')
            ->pluck('total', 'parent_id');

        // Get monthly bank transfer amounts per leader
        $monthlyBankTransfers = DB::table('bank_transfers')
            ->join('users', 'bank_transfers.agent_id', '=', 'users.id')
            ->select('users.parent_id', DB::raw('SUM(bank_transfers.amount) as total'))
            ->whereIn('users.parent_id', $leaderIds)
            ->where('bank_transfers.status', 'approved')
            ->whereBetween('bank_transfers.created_at', [$startOfMonth, $endOfMonth])
            ->groupBy('users.parent_id')
            ->pluck('total', 'parent_id');

        $leadersData = $leaders->getCollection()->map(function($leader) use (
            $agentCounts, $dailyShops, $monthlyShops, $dailyRewardPasses, 
            $monthlyRewardPasses, $dailyBankTransfers, $monthlyBankTransfers
        ) {
            return [
                'id' => $leader->id,
                'unique_id' => $leader->unique_id,
                'name' => $leader->name,
                'mobile' => $leader->mobile,
                'email' => $leader->email,
                'is_blocked' => $leader->is_blocked,
                'created_at' => $leader->created_at,
                'total_agents' => $agentCounts[$leader->id] ?? 0,
                'counts' => [
                    'shop_onboarding' => [
                        'daily' => $dailyShops[$leader->id] ?? 0,
                        'monthly' => $monthlyShops[$leader->id] ?? 0
                    ],
                    'reward_passes' => [
                        'daily' => $dailyRewardPasses[$leader->id] ?? 0,
                        'monthly' => $monthlyRewardPasses[$leader->id] ?? 0
                    ],
                    'bank_transfers' => [
                        'daily' => (float) ($dailyBankTransfers[$leader->id] ?? 0),
                        'monthly' => (float) ($monthlyBankTransfers[$leader->id] ?? 0)
                    ]
                ]
            ];
        });

        $leaders->setCollection($leadersData);

        return response()->json([
            'success' => true,
            'message' => 'Leaders retrieved successfully',
            'data' => $leaders,
            'total' => $leaders->count()
        ]);
    }

    /**
     * Get all agents under a leader with necessary details and counts
     */
    public function getAgentsUnderLeader(Request $request): JsonResponse
    {
        if ($request->user()->role !== 'leader' && $request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Only leaders and admins can access this endpoint'
            ], 403);
        }
        
        if ($request->leader_id && $request->user()->role === 'admin') {
            $leader = User::where('role', 'leader')->where('id', $request->leader_id)->first();
            if (!$leader) {
                return response()->json([
                    'success' => false,
                    'message' => 'No leader found with the provided ID'
                ], 404);
            }
        } else {
            $leader = $request->user();
            if ($leader->role !== 'leader') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only leaders can access their agents'
                ], 403);
            }
        }

        $today = now()->format('Y-m-d');
        $startOfMonth = now()->startOfMonth()->format('Y-m-d');
        $endOfMonth = now()->endOfMonth()->format('Y-m-d');

        $agents = $leader->agents()->get();
        $agentIds = $agents->pluck('id')->toArray();

        // Get daily shop counts per agent
        $dailyShops = DB::table('shops')
            ->select('agent_id', DB::raw('COUNT(*) as count'))
            ->whereIn('agent_id', $agentIds)
            ->where('status', 'approved')
            ->whereDate('created_at', $today)
            ->groupBy('agent_id')
            ->pluck('count', 'agent_id');

        // Get monthly shop counts per agent
        $monthlyShops = DB::table('shops')
            ->select('agent_id', DB::raw('COUNT(*) as count'))
            ->whereIn('agent_id', $agentIds)
            ->where('status', 'approved')
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->groupBy('agent_id')
            ->pluck('count', 'agent_id');

        // Get total shop counts per agent
        $totalShops = DB::table('shops')
            ->select('agent_id', DB::raw('COUNT(*) as count'))
            ->whereIn('agent_id', $agentIds)
            ->where('status', 'approved')
            ->groupBy('agent_id')
            ->pluck('count', 'agent_id');

        // Get daily bank transfer amounts per agent
        $dailyBankTransfers = DB::table('bank_transfers')
            ->select('agent_id', DB::raw('SUM(amount) as total'))
            ->whereIn('agent_id', $agentIds)
            ->where('status', 'approved')
            ->whereDate('created_at', $today)
            ->groupBy('agent_id')
            ->pluck('total', 'agent_id');

        // Get monthly bank transfer amounts per agent
        $monthlyBankTransfers = DB::table('bank_transfers')
            ->select('agent_id', DB::raw('SUM(amount) as total'))
            ->whereIn('agent_id', $agentIds)
            ->where('status', 'approved')
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->groupBy('agent_id')
            ->pluck('total', 'agent_id');

        // Get total bank transfer amounts per agent
        $totalBankTransfers = DB::table('bank_transfers')
            ->select('agent_id', DB::raw('SUM(amount) as total'))
            ->whereIn('agent_id', $agentIds)
            ->where('status', 'approved')
            ->groupBy('agent_id')
            ->pluck('total', 'agent_id');

        // Get daily reward passes per agent
        $dailyRewardPasses = DB::table('reward_passes')
            ->select('agent_id', DB::raw('COUNT(*) as count'))
            ->whereIn('agent_id', $agentIds)
            ->where('status', 'approved')
            ->whereDate('created_at', $today)
            ->groupBy('agent_id')
            ->pluck('count', 'agent_id');

        // Get monthly reward passes per agent
        $monthlyRewardPasses = DB::table('reward_passes')
            ->select('agent_id', DB::raw('COUNT(*) as count'))
            ->whereIn('agent_id', $agentIds)
            ->where('status', 'approved')
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->groupBy('agent_id')
            ->pluck('count', 'agent_id');

        // Get total reward passes per agent
        $totalRewardPasses = DB::table('reward_passes')
            ->select('agent_id', DB::raw('COUNT(*) as count'))
            ->whereIn('agent_id', $agentIds)
            ->where('status', 'approved')
            ->groupBy('agent_id')
            ->pluck('count', 'agent_id');

        $agentsData = $agents->map(function ($agent) use (
            $dailyShops, $monthlyShops, $totalShops,
            $dailyBankTransfers, $monthlyBankTransfers, $totalBankTransfers,
            $dailyRewardPasses, $monthlyRewardPasses, $totalRewardPasses
        ) {
            return [
                'id' => $agent->id,
                'unique_id' => $agent->unique_id,
                'name' => $agent->name,
                'mobile' => $agent->mobile,
                'email' => $agent->email,
                'is_blocked' => $agent->is_blocked,
                'created_at' => $agent->created_at,
                'counts' => [
                    'shop_onboarding' => [
                        'daily' => $dailyShops[$agent->id] ?? 0,
                        'monthly' => $monthlyShops[$agent->id] ?? 0,
                        'total' => $totalShops[$agent->id] ?? 0
                    ],
                    'bank_transfers' => [
                        'daily' => (float) ($dailyBankTransfers[$agent->id] ?? 0),
                        'monthly' => (float) ($monthlyBankTransfers[$agent->id] ?? 0),
                        'total' => (float) ($totalBankTransfers[$agent->id] ?? 0)
                    ],
                    'reward_passes' => [
                        'daily' => $dailyRewardPasses[$agent->id] ?? 0,
                        'monthly' => $monthlyRewardPasses[$agent->id] ?? 0,
                        'total' => $totalRewardPasses[$agent->id] ?? 0
                    ]
                ]
            ];
        });

        // Calculate summary for the leader
        $summary = [
            'total_agents' => $agentsData->count(),
            'total_shops_today' => $agentsData->sum(fn($agent) => $agent['counts']['shop_onboarding']['daily']),
            'total_shops_this_month' => $agentsData->sum(fn($agent) => $agent['counts']['shop_onboarding']['monthly']),
            'total_shops_all_time' => $agentsData->sum(fn($agent) => $agent['counts']['shop_onboarding']['total']),
            'total_bt_today' => (float) $agentsData->sum(fn($agent) => $agent['counts']['bank_transfers']['daily']),
            'total_bt_this_month' => (float) $agentsData->sum(fn($agent) => $agent['counts']['bank_transfers']['monthly']),
            'total_bt_all_time' => (float) $agentsData->sum(fn($agent) => $agent['counts']['bank_transfers']['total']),
            'total_reward_passes_today' => $agentsData->sum(fn($agent) => $agent['counts']['reward_passes']['daily']),
            'total_reward_passes_this_month' => $agentsData->sum(fn($agent) => $agent['counts']['reward_passes']['monthly']),
            'total_reward_passes_all_time' => $agentsData->sum(fn($agent) => $agent['counts']['reward_passes']['total'])
        ];

        return response()->json([
            'success' => true,
            'message' => 'Agents retrieved successfully',
            'data' => $agentsData,
            'summary' => $summary
        ]);
    }

    /**
     * Get my parent (leader for agent, admin for leader)
     */
    public function getMyParent(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if ($user->role === 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Admin users do not have a parent'
            ], 400);
        }

        $parent = $user->parent;

        if (!$parent) {
            return response()->json([
                'success' => false,
                'message' => 'No parent found for this user'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Parent retrieved successfully',
            'data' => $parent
        ]);
    }

    /**
     * Get complete hierarchy tree for admin
     */
    public function getCompleteHierarchy(Request $request): JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Only admins can access complete hierarchy'
            ], 403);
        }

        $admin = $request->user();
        $leaders = $admin->leaders()->get();
        $leaderIds = $leaders->pluck('id')->toArray();

        // Get all agents under these leaders
        $totalAgents = DB::table('users')
            ->whereIn('parent_id', $leaderIds)
            ->where('role', 'agent')
            ->count();

        // Get agent IDs for further queries
        $agentIds = DB::table('users')
            ->whereIn('parent_id', $leaderIds)
            ->where('role', 'agent')
            ->pluck('id')
            ->toArray();

        // Get total approved shops
        $totalShops = DB::table('shops')
            ->whereIn('agent_id', $agentIds)
            ->where('status', 'approved')
            ->count();

        // Get total approved bank transfer amount
        $totalBankTransfers = DB::table('bank_transfers')
            ->whereIn('agent_id', $agentIds)
            ->where('status', 'approved')
            ->sum('amount');

        // Get total approved reward passes
        $totalRewardPasses = DB::table('reward_passes')
            ->whereIn('agent_id', $agentIds)
            ->where('status', 'approved')
            ->count();

        return response()->json([
            'success' => true,
            'message' => 'Complete hierarchy retrieved successfully',
            'data' => [
                'admin' => $admin,
                'leaders' => $leaders,
                'statistics' => [
                    'total_leaders' => $leaders->count(),
                    'total_agents' => $totalAgents,
                    'total_shops' => $totalShops,
                    'total_bank_transfers' => (float) $totalBankTransfers,
                    'total_reward_passes' => $totalRewardPasses
                ]
            ]
        ]);
    }
}