<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Shop;
use App\Models\BankTransfer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TestController extends Controller
{
    /**
     * Test the new relationship structure
     */
    public function testRelationships(Request $request): JsonResponse
    {
        $results = [];

        // Test 1: Create a simple hierarchy (Admin -> Leader -> Agent)
        try {
            // Find or create an admin user
            $admin = User::where('role', 'admin')->first();
            
            if ($admin) {
                $results['admin'] = [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'leaders_count' => $admin->leaders()->count()
                ];

                // Get a leader under this admin
                $leader = $admin->leaders()->first();
                if ($leader) {
                    $results['leader'] = [
                        'id' => $leader->id,
                        'name' => $leader->name,
                        'parent_id' => $leader->parent_id,
                        'agents_count' => $leader->agents()->count()
                    ];

                    // Get an agent under this leader
                    $agent = $leader->agents()->first();
                    if ($agent) {
                        $results['agent'] = [
                            'id' => $agent->id,
                            'name' => $agent->name,
                            'parent_id' => $agent->parent_id,
                            'parent_name' => $agent->parent ? $agent->parent->name : null,
                            'shops_count' => $agent->shops()->count()
                        ];

                        // Get a shop by this agent
                        $shop = $agent->shops()->first();
                        if ($shop) {
                            $results['shop'] = [
                                'id' => $shop->id,
                                'customer_name' => $shop->customer_name,
                                'agent_name' => $shop->agent->name,
                                'team_leader_name' => $shop->team_leader ? $shop->team_leader->name : 'No team leader',
                                'bank_transfers_count' => $shop->bankTransfers()->count()
                            ];
                        }
                    }
                }
            }

            // Test accessing team leader via accessor
            $sampleShop = Shop::with('agent.parent')->first();
            if ($sampleShop) {
                $results['sample_shop_test'] = [
                    'shop_id' => $sampleShop->id,
                    'agent_name' => $sampleShop->agent->name,
                    'team_leader_via_accessor' => $sampleShop->team_leader ? $sampleShop->team_leader->name : 'No team leader',
                    'team_leader_via_parent' => $sampleShop->agent->parent ? $sampleShop->agent->parent->name : 'No parent'
                ];
            }

            // Test bank transfer relationships
            $sampleTransfer = BankTransfer::with('agent.parent')->first();
            if ($sampleTransfer) {
                $results['sample_transfer_test'] = [
                    'transfer_id' => $sampleTransfer->id,
                    'agent_name' => $sampleTransfer->agent->name,
                    'team_leader_via_accessor' => $sampleTransfer->team_leader ? $sampleTransfer->team_leader->name : 'No team leader'
                ];
            }

        } catch (\Exception $e) {
            $results['error'] = $e->getMessage();
        }

        return response()->json([
            'success' => true,
            'message' => 'Relationship test completed',
            'data' => $results
        ]);
    }
}
