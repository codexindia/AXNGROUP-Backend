<?php

namespace App\Http\Controllers\Api\BankTransfer;

use App\Http\Controllers\Controller;
use App\Models\BankTransfer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Services\PayoutService;

class BankTransferController extends Controller
{
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_name' => 'required|string|max:255',
            'customer_mobile' => 'required|string|max:15',
            'shop_name' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Only agents can create bank transfer requests
        if ($request->user()->role !== 'agent') {
            return response()->json([
                'success' => false,
                'message' => 'Only agents can create bank transfer requests'
            ], 403);
        }

        $bankTransfer = BankTransfer::create([
            'agent_id' => $request->user()->id,
            'customer_name' => $request->customer_name,
            'customer_mobile' => $request->customer_mobile,
            'shop_name' => $request->shop_name,
            'amount' => $request->amount,
            'status' => 'pending'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Bank transfer request created successfully',
            'data' => $bankTransfer->load(['agent.parent'])
        ], 201);
    }

    public function getByAgent(Request $request)
    {
        $bankTransfers = BankTransfer::where('agent_id', $request->user()->id)
                                   ->with(['agent.parent'])
                                   ->orderBy('created_at', 'desc')
                                   ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $bankTransfers
        ]);
    }

    public function getByLeader(Request $request)
    {
        if ($request->user()->role !== 'leader') {
            return response()->json([
                'success' => false,
                'message' => 'Only leaders can view their assigned bank transfers'
            ], 403);
        }

        // Get all agents under this leader first
        $agentIds = $request->user()->agents()->pluck('id');

        $bankTransfers = BankTransfer::whereIn('agent_id', $agentIds)
                                   ->with(['agent'])
                                   ->orderBy('created_at', 'desc')
                                   ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $bankTransfers
        ]);
    }

    public function show($id)
    {
        $bankTransfer = BankTransfer::with(['agent.parent'])->find($id);

        if (!$bankTransfer) {
            return response()->json([
                'success' => false,
                'message' => 'Bank transfer not found'
            ], 404);
        }

        // Check if user has permission to view this bank transfer
        $user = auth()->user();
        $isAgentOwner = $bankTransfer->agent_id === $user->id;
        $isTeamLeader = $user->role === 'leader' && $bankTransfer->agent->parent_id === $user->id;
        $isAdmin = in_array($user->role, ['admin']);
        
        if (!$isAgentOwner && !$isTeamLeader && !$isAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view this bank transfer'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $bankTransfer
        ]);
    }

    private function creditAgentWallet($bankTransfer)
    {
        // This method is now handled by PayoutService in adminApproval
        // Keeping for backward compatibility but logic moved to admin approval
    }

    /**
     * Admin approval for bank transfer (triggers payout)
     */
    public function adminApproval(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:approved,rejected',
            'admin_remark' => 'nullable|string',
            'amount_change_remark' => 'nullable|string'
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
                'message' => 'Only admin can approve/reject bank transfers'
            ], 403);
        }

        $bankTransfer = BankTransfer::find($id);

        if (!$bankTransfer) {
            return response()->json([
                'success' => false,
                'message' => 'Bank transfer not found'
            ], 404);
        }

        // Bank transfer must be pending
        if ($bankTransfer->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Bank transfer has already been processed'
            ], 400);
        }

        $bankTransfer->update([
            'status' => $request->status,
            'amount_change_remark' => $request->amount_change_remark,
            'reject_remark' => $request->status === 'rejected' ? $request->admin_remark : null
        ]);

        // If admin approved, calculate and credit payout
        if ($request->status === 'approved') {
            // Check if transfer is eligible for payout
            if (PayoutService::isBankTransferEligible($bankTransfer->amount)) {
                // Get agent's total bank transfer amount for current month
                $totalAmount = PayoutService::getAgentBankTransferTotal($bankTransfer->agent_id);
                
                // Calculate payout based on total monthly amount
                $payoutAmount = PayoutService::calculateBankTransferPayout($totalAmount);
                
                if ($payoutAmount > 0) {
                    PayoutService::creditPayout(
                        $bankTransfer->agent_id, 
                        $payoutAmount, 
                        'bank_transfer_payout', 
                        $bankTransfer->id
                    );
                }
            } else {
                // For transfers below ₹50,000, just note the fee deduction
                $feeDeduction = PayoutService::calculateFeeDeduction($bankTransfer->amount);
                // Fee deduction logic can be handled in reporting, not stored in DB
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Bank transfer approval updated successfully',
            'data' => $bankTransfer->load(['agent.parent'])
        ]);
    }

    /**
     * Get pending bank transfers for admin approval
     */
    public function getPendingForAdmin()
    {
        $bankTransfers = BankTransfer::where('status', 'pending') // Direct pending, no leader approval needed
                                   ->with(['agent.parent'])
                                   ->orderBy('created_at', 'asc')
                                   ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $bankTransfers
        ]);
    }
}