<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RelationshipService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class RelationshipController extends Controller
{
    protected $relationshipService;

    public function __construct(RelationshipService $relationshipService)
    {
        $this->relationshipService = $relationshipService;
    }

    /**
     * Get all agents under a specific leader
     */
    public function getAgentsUnderLeader(int $leaderId): JsonResponse
    {
        try {
            $agents = $this->relationshipService->getAgentsUnderLeader($leaderId);
            
            return response()->json([
                'success' => true,
                'message' => 'Agents retrieved successfully',
                'data' => $agents,
                'total' => $agents->count()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving agents: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get complete hierarchy for a leader
     */
    public function getLeaderHierarchy(int $leaderId): JsonResponse
    {
        try {
            $hierarchy = $this->relationshipService->getCompleteHierarchy($leaderId);
            
            if (empty($hierarchy)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Leader not found or invalid'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Leader hierarchy retrieved successfully',
                'data' => $hierarchy
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving hierarchy: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all bank transfers for a specific shop
     */
    public function getShopBankTransfers(int $shopId): JsonResponse
    {
        try {
            $transfers = $this->relationshipService->getBankTransfersByShop($shopId);
            
            return response()->json([
                'success' => true,
                'message' => 'Shop bank transfers retrieved successfully',
                'data' => $transfers,
                'total' => $transfers->count(),
                'total_amount' => $transfers->sum('amount')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving bank transfers: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all bank transfers by agent with shop information
     */
    public function getAgentBankTransfers(int $agentId): JsonResponse
    {
        try {
            $transfers = $this->relationshipService->getBankTransfersByAgent($agentId);
            
            return response()->json([
                'success' => true,
                'message' => 'Agent bank transfers retrieved successfully',
                'data' => $transfers,
                'total' => $transfers->count(),
                'total_amount' => $transfers->sum('amount')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving bank transfers: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if agent belongs to leader
     */
    public function checkAgentLeaderRelation(int $agentId, int $leaderId): JsonResponse
    {
        try {
            $belongsToLeader = $this->relationshipService->isAgentUnderLeader($agentId, $leaderId);
            
            return response()->json([
                'success' => true,
                'message' => 'Relationship checked successfully',
                'data' => [
                    'agent_id' => $agentId,
                    'leader_id' => $leaderId,
                    'belongs_to_leader' => $belongsToLeader
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error checking relationship: ' . $e->getMessage()
            ], 500);
        }
    }
}
