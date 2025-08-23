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
     * Get all agents under a leader (simplified)
     */
    public function getAgentsUnderLeader(Request $request): JsonResponse
    {
        if ($request->user()->role !== 'leader') {
            return response()->json([
                'success' => false,
                'message' => 'Only leaders can access this endpoint'
            ], 403);
        }

        $agents = $request->user()->agents()->with(['shops', 'bankTransfers'])->get();

        return response()->json([
            'success' => true,
            'message' => 'Agents retrieved successfully',
            'data' => $agents,
            'total' => $agents->count(),
            'total_shops' => $agents->sum(function($agent) { return $agent->shops->count(); }),
            'total_bank_transfers' => $agents->sum(function($agent) { return $agent->bankTransfers->count(); })
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
