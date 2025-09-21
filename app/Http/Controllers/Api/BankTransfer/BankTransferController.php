<?php

namespace App\Http\Controllers\Api\BankTransfer;

use App\Http\Controllers\Controller;
use App\Models\BankTransfer;
use App\Models\MonthlyBtSheetData;
use App\Models\SheetData;
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
            'amount' => 'required|numeric|min:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
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

        // Check daily limit (max 1 lakh per day)
        $dailyLimit = 100000; // 1 lakh
        $today = now()->format('Y-m-d');
        $todayTotal = BankTransfer::where('customer_mobile', $request->customer_mobile)
            ->where('status', '!=', 'rejected')
            ->whereDate('created_at', $today)
            ->sum('amount');

        if (($todayTotal + $request->amount) > $dailyLimit) {
            return response()->json([
            'success' => false,
            'message' => 'Daily limit exceeded. Maximum ₹1,00,000 per day allowed. Current today total: ₹' . number_format($todayTotal)
            ], 429);
        }

        // Check monthly limit (max 2 lakh per month)
        $monthlyLimit = 200000; // 2 lakh
        $currentMonth = now()->format('Y-m');
        $monthlyTotal = BankTransfer::where('customer_mobile', $request->customer_mobile)
            ->where('status', '!=', 'rejected')
            ->whereRaw("DATE_FORMAT(created_at, '%Y-%m') = ?", [$currentMonth])
            ->sum('amount');

        if (($monthlyTotal + $request->amount) > $monthlyLimit) {
            return response()->json([
            'success' => false,
            'message' => 'Monthly limit exceeded. Maximum ₹2,00,000 per month allowed. Current month total: ₹' . number_format($monthlyTotal)
            ], 429);
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
        //date filter added
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $bankTransfers = BankTransfer::where('agent_id', $request->user()->id)
                                   ->with(['agent.parent'])
                                   ->when($startDate, function ($query) use ($startDate) {
                                       return $query->where('created_at', '>=', $startDate);
                                   })
                                   ->when($endDate, function ($query) use ($endDate) {
                                       return $query->where('created_at', '<=', $endDate);
                                   })
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

    /**
     * Admin approval for bank transfer (triggers payout)
     */
    public function adminApproval(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:approved,rejected',
            'admin_remark' => 'nullable|string',
            'amount_change_remark' => 'nullable|string',
            'amount' => 'nullable|numeric|min:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
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
        // If amount is changed by admin, update it
        if ($request->has('amount') && $request->amount != $bankTransfer->amount && $request->amount >= 500) {
            $bankTransfer->amount = $request->amount;
            $bankTransfer->save();
        }

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
     * Get pending bank transfers for admin approval (grouped by mobile number)
     */
    public function getPendingForAdmin()
    {
        $startDate = request()->query('start_date');
        $endDate = request()->query('end_date');
        $needAll = request()->query('need_all');

        if($needAll){
        $query = BankTransfer::with(['agent:id,name,mobile,parent_id', 'agent.parent:id,name,mobile']);
        }else{
          $query = BankTransfer::where('status', 'pending')
                        ->with(['agent:id,name,mobile,parent_id', 'agent.parent:id,name,mobile']);
          
        }
        // Apply date filters if provided
        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        $bankTransfers = $query->orderBy('created_at', 'desc')->get();

        // Group by customer mobile number
        $groupedTransfers = $bankTransfers->groupBy('customer_mobile')->map(function ($transfers, $mobileNo) {
            $totalAmount = $transfers->sum('amount');
            
            // Get actual amount from monthly_bt_sheet_data table for current month
            $currentMonth = now()->month;
            $currentYear = now()->year;
            
            $monthlyData = MonthlyBtSheetData::where('mobile_no', $mobileNo)
                                           ->where('year', $currentYear)
                                           ->where('month', $currentMonth)
                                           ->first();
            
            $totalActualAmount = $monthlyData ? $monthlyData->total_bank_transfer : 0;
            $dailyActualAmount = 0;
            $rows = $transfers->map(function ($transfer) use ($totalActualAmount, $transfers, &$dailyActualAmount) {
                // Get individual actual amount from daily sheet_data table
                $transferDate = $transfer->created_at->format('Y-m-d');
                $dailySheetData = SheetData::where('cus_no', $transfer->customer_mobile)
                                         ->where('date', $transferDate)
                                         ->first();
                
                $individualActualAmount = $dailySheetData ? $dailySheetData->actual_bt_tide : 0;
                $dailyActualAmount += $individualActualAmount;

                return [
                    'id' => $transfer->id,
                    'name' => $transfer->customer_name,
                    'amount' => $transfer->amount,
                    'actual_amount' => round($individualActualAmount, 2),
                    'fse_name' => $transfer->agent->name ?? 'N/A', // FSE = Agent
                    'tl_name' => $transfer->agent->parent->name ?? 'N/A', // TL = Team Leader
                    'status' => strtoupper($transfer->status),
                    'created_at' => $transfer->created_at->format('Y-m-d H:i:s')
                ];
            });

            return [
                'mobile_no' => $mobileNo,
                'total_amount' => $totalAmount,
                'actual_amount' => $dailyActualAmount,
                'total_actual_amount' => $totalActualAmount,
                'rows' => $rows->values()
            ];
        });

        // Convert to indexed array and add pagination-like structure
        $result = $groupedTransfers->values();
        
        // Simple pagination simulation
        $perPage = request()->query('per_page', 20);
        $page = request()->query('page', 1);
        $total = $result->count();
        $offset = ($page - 1) * $perPage;
        $paginatedResult = $result->slice($offset, $perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'current_page' => (int) $page,
                'data' => $paginatedResult->values(),
                'total' => $total,
                'per_page' => (int) $perPage,
                'last_page' => ceil($total / $perPage),
                'from' => $offset + 1,
                'to' => min($offset + $perPage, $total)
            ]
        ]);
    }
}