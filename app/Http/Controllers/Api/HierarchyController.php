<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

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

        $leaders = $request->user()->leaders()->with('agents')->get();

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
        if ($request->user()->role !== 'leader') {
            return response()->json([
                'success' => false,
                'message' => 'Only leaders can access this endpoint'
            ], 403);
        }

        $agents = $request->user()->agents()->get();

        $agentsData = $agents->map(function($agent) {
            // Get today's date
            $today = now()->format('Y-m-d');
            $startOfMonth = now()->startOfMonth()->format('Y-m-d');
            $endOfMonth = now()->endOfMonth()->format('Y-m-d');

            // Daily counts
            $dailyShops = $agent->shops()->whereDate('created_at', $today)->count();
            $dailyBankTransfers = $agent->bankTransfers()->whereDate('created_at', $today)->count();

            // Monthly counts
            $monthlyShops = $agent->shops()->whereBetween('created_at', [$startOfMonth, $endOfMonth])->count();
            $monthlyBankTransfers = $agent->bankTransfers()->whereBetween('created_at', [$startOfMonth, $endOfMonth])->count();

            // Total counts
            $totalShops = $agent->shops()->count();
            $totalBankTransfers = $agent->bankTransfers()->count();

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
                        'daily' => $dailyShops,
                        'monthly' => $monthlyShops,
                        'total' => $totalShops
                    ],
                    'bank_transfers' => [
                        'daily' => $dailyBankTransfers,
                        'monthly' => $monthlyBankTransfers,
                        'total' => $totalBankTransfers
                    ]
                ]
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Agents retrieved successfully',
            'data' => $agentsData,
           
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
        $leaders = $admin->leaders()->with([
            'agents.shops.bankTransfers',
            'agents.bankTransfers'
        ])->get();

        $totalAgents = $leaders->sum(function($leader) {
            return $leader->agents->count();
        });

        $totalShops = $leaders->sum(function($leader) {
            return $leader->agents->sum(function($agent) {
                return $agent->shops->count();
            });
        });

        $totalBankTransfers = $leaders->sum(function($leader) {
            return $leader->agents->sum(function($agent) {
                return $agent->bankTransfers->count();
            });
        });

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
                    'total_bank_transfers' => $totalBankTransfers
                ]
            ]
        ]);
    }
}
