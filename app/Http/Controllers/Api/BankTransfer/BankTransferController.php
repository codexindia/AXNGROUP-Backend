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
            'amount' => 'required|numeric|min:1',
            'team_leader_id' => 'required|exists:users,id'
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
            'team_leader_id' => $request->team_leader_id,
            'customer_name' => $request->customer_name,
            'customer_mobile' => $request->customer_mobile,
            'amount' => $request->amount,
            'status' => 'pending'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Bank transfer request created successfully',
            'data' => $bankTransfer->load(['agent', 'teamLeader'])
        ], 201);
    }

    public function getByAgent(Request $request)
    {
        $bankTransfers = BankTransfer::where('agent_id', $request->user()->id)
                                   ->with(['teamLeader'])
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

        $bankTransfers = BankTransfer::where('team_leader_id', $request->user()->id)
                                   ->with(['agent'])
                                   ->orderBy('created_at', 'desc')
                                   ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $bankTransfers
        ]);
    }

    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:approved,rejected',
            'amount_change_remark' => 'nullable|string',
            'reject_remark' => 'required_if:status,rejected|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $bankTransfer = BankTransfer::find($id);

        if (!$bankTransfer) {
            return response()->json([
                'success' => false,
                'message' => 'Bank transfer not found'
            ], 404);
        }

        // Only the assigned team leader can update status
        if ($request->user()->role !== 'leader' || $bankTransfer->team_leader_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to update this bank transfer status'
            ], 403);
        }

        $bankTransfer->update([
            'status' => $request->status,
            'amount_change_remark' => $request->amount_change_remark,
            'reject_remark' => $request->status === 'rejected' ? $request->reject_remark : null
        ]);

        // If approved, credit agent's wallet
        if ($request->status === 'approved') {
            $this->creditAgentWallet($bankTransfer);
        }

        return response()->json([
            'success' => true,
            'message' => 'Bank transfer status updated successfully',
            'data' => $bankTransfer->load(['agent', 'teamLeader'])
        ]);
    }

    public function show($id)
    {
        $bankTransfer = BankTransfer::with(['agent', 'teamLeader'])->find($id);

        if (!$bankTransfer) {
            return response()->json([
                'success' => false,
                'message' => 'Bank transfer not found'
            ], 404);
        }

        // Check if user has permission to view this bank transfer
        $user = auth()->user();
        if ($bankTransfer->agent_id !== $user->id && $bankTransfer->team_leader_id !== $user->id && !in_array($user->role, ['admin'])) {
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
     * Admin final approval for bank transfer (triggers payout)
     */
    public function adminApproval(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:admin_approved,admin_rejected',
            'admin_remark' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Only admin can do final approval
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Only admin can do final approval'
            ], 403);
        }

        $bankTransfer = BankTransfer::find($id);

        if (!$bankTransfer) {
            return response()->json([
                'success' => false,
                'message' => 'Bank transfer not found'
            ], 404);
        }

        // Bank transfer must be approved by leader first
        if ($bankTransfer->status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Bank transfer must be approved by team leader first'
            ], 400);
        }

        $bankTransfer->update([
            'status' => $request->status
        ]);

        // If admin approved, calculate and credit payout
        if ($request->status === 'admin_approved') {
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
            'message' => 'Bank transfer admin approval updated successfully',
            'data' => $bankTransfer->load(['agent', 'teamLeader'])
        ]);
    }

    /**
     * Get pending bank transfers for admin approval
     */
    public function getPendingForAdmin()
    {
        $bankTransfers = BankTransfer::where('status', 'approved') // Leader approved, waiting for admin
                                   ->with(['agent', 'teamLeader'])
                                   ->orderBy('updated_at', 'asc')
                                   ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $bankTransfers
        ]);
    }
}