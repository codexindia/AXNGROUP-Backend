<?php

namespace App\Services;

use App\Models\User;
use App\Models\Shop;
use App\Models\BankTransfer;
use Illuminate\Database\Eloquent\Collection;

class RelationshipService
{
    /**
     * Get all agents under a specific leader
     */
    public function getAgentsUnderLeader(int $leaderId): Collection
    {
        return User::where('parent_id', $leaderId)
                   ->where('role', 'agent')
                   ->get();
    }

    /**
     * Get all shops managed by a specific leader
     */
    public function getShopsByLeader(int $leaderId): Collection
    {
        // Get all agents under this leader first
        $agentIds = User::where('parent_id', $leaderId)
                       ->where('role', 'agent')
                       ->pluck('id');

        return Shop::whereIn('agent_id', $agentIds)->with(['agent'])->get();
    }

    /**
     * Get all shops created by a specific agent
     */
    public function getShopsByAgent(int $agentId): Collection
    {
        return Shop::where('agent_id', $agentId)->get();
    }

    /**
     * Get all bank transfers by a specific agent
     */
    public function getBankTransfersByAgent(int $agentId): Collection
    {
        return BankTransfer::where('agent_id', $agentId)
                          ->with(['agent.parent'])
                          ->get();
    }

    /**
     * Get all bank transfers managed by a specific leader
     */
    public function getBankTransfersByLeader(int $leaderId): Collection
    {
        // Get all agents under this leader first
        $agentIds = User::where('parent_id', $leaderId)
                       ->where('role', 'agent')
                       ->pluck('id');

        return BankTransfer::whereIn('agent_id', $agentIds)
                          ->with(['agent'])
                          ->get();
    }

    /**
     * Get leader information for a specific agent (through parent relationship)
     */
    public function getLeaderForAgent(int $agentId): ?User
    {
        $agent = User::find($agentId);
        return $agent ? $agent->parent : null;
    }

    /**
     * Check if a specific agent belongs to a specific leader
     */
    public function isAgentUnderLeader(int $agentId, int $leaderId): bool
    {
        return User::where('id', $agentId)
                   ->where('parent_id', $leaderId)
                   ->where('role', 'agent')
                   ->exists();
    }

    /**
     * Get complete hierarchy: Leader -> Agents -> Shops -> Bank Transfers
     */
    public function getCompleteHierarchy(int $leaderId): array
    {
        $leader = User::find($leaderId);
        if (!$leader || $leader->role !== 'leader') {
            return [];
        }

        $agents = $this->getAgentsUnderLeader($leaderId);
        $shops = $this->getShopsByLeader($leaderId);
        $bankTransfers = $this->getBankTransfersByLeader($leaderId);

        return [
            'leader' => $leader,
            'agents' => $agents,
            'shops' => $shops,
            'bank_transfers' => $bankTransfers,
            'total_agents' => $agents->count(),
            'total_shops' => $shops->count(),
            'total_bank_transfers' => $bankTransfers->count(),
            'total_transfer_amount' => $bankTransfers->sum('amount')
        ];
    }
}
