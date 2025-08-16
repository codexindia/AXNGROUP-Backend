<?php

namespace App\Http\Controllers\Api\Shop;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ShopController extends Controller
{
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_name' => 'required|string|max:255',
            'customer_mobile' => 'required|string|max:15',
            'team_leader_id' => 'required|exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Only agents can create shop onboarding
        if ($request->user()->role !== 'agent') {
            return response()->json([
                'success' => false,
                'message' => 'Only agents can create shop onboarding'
            ], 403);
        }

        $shop = Shop::create([
            'agent_id' => $request->user()->id,
            'team_leader_id' => $request->team_leader_id,
            'customer_name' => $request->customer_name,
            'customer_mobile' => $request->customer_mobile,
            'status' => 'pending'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Shop onboarding request created successfully',
            'data' => $shop->load(['agent', 'teamLeader'])
        ], 201);
    }

    public function getByAgent(Request $request)
    {
        $shops = Shop::where('agent_id', $request->user()->id)
                    ->with(['teamLeader'])
                    ->orderBy('created_at', 'desc')
                    ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $shops
        ]);
    }

    public function getByLeader(Request $request)
    {
        if ($request->user()->role !== 'leader') {
            return response()->json([
                'success' => false,
                'message' => 'Only leaders can view their assigned shops'
            ], 403);
        }

        $shops = Shop::where('team_leader_id', $request->user()->id)
                    ->with(['agent'])
                    ->orderBy('created_at', 'desc')
                    ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $shops
        ]);
    }

    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:approved,rejected',
            'reject_remark' => 'required_if:status,rejected|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $shop = Shop::find($id);

        if (!$shop) {
            return response()->json([
                'success' => false,
                'message' => 'Shop not found'
            ], 404);
        }

        // Only the assigned team leader can update status
        if ($request->user()->role !== 'leader' || $shop->team_leader_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to update this shop status'
            ], 403);
        }

        $shop->update([
            'status' => $request->status,
            'reject_remark' => $request->status === 'rejected' ? $request->reject_remark : null
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Shop status updated successfully',
            'data' => $shop->load(['agent', 'teamLeader'])
        ]);
    }

    public function show($id)
    {
        $shop = Shop::with(['agent', 'teamLeader'])->find($id);

        if (!$shop) {
            return response()->json([
                'success' => false,
                'message' => 'Shop not found'
            ], 404);
        }

        // Check if user has permission to view this shop
        $user = auth()->user();
        if ($shop->agent_id !== $user->id && $shop->team_leader_id !== $user->id && !in_array($user->role, ['admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view this shop'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $shop
        ]);
    }
}