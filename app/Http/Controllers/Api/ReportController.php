<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Shop;
use App\Models\BankTransfer;
use App\Models\RewardPass;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportController extends Controller
{
    /**
     * Get monthly onboarding report for an agent
     * Shows customer data with refer code and qr transactions
     */
    public function getOnboardingMonthlyReport(Request $request)
    {
        $user = $request->user();

        // If the user is an agent, show their own data
        // If leader/admin, they can optionally specify an agent_id
        $agentId = $user->role === 'agent' ? $user->id : $request->input('agent_id', $user->id);

        // Validate year and month (optional filters)
        $year = $request->input('year', date('Y'));
        $month = $request->input('month', date('m'));

        // Build query for onboarding data
        $query = Shop::where('agent_id', $agentId)
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->with('onboardingSheetData');

        // Get detailed onboarding records
        $onboardingRecords = $query->get()->map(function ($shop, $index) {
            return [
                'sn' => $index + 1,
                'customer_name' => $shop->customer_name,
                'mobile_no' => $shop->customer_mobile,
                'refer_code' => $shop->onboardingSheetData->refer_code ?? 'N/A',
                'qr_trx' => $shop->onboardingSheetData->qr_trx ?? 0,
                'status' => $shop->status,
                'created_at' => $shop->created_at->format('Y-m-d H:i:s'),
            ];
        });

        // Get monthly summary
        $summary = [
            'total_onboarding' => $query->count(),
            'approved' => $query->where('status', 'approved')->count(),
            'pending' => $query->where('status', 'pending')->count(),
            'rejected' => $query->where('status', 'rejected')->count(),
            'total_qr_trx' => $onboardingRecords->sum('qr_trx'),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Onboarding monthly report retrieved successfully',
            'data' => [
                'agent_id' => $agentId,
                'year' => (int) $year,
                'month' => (int) $month,
                'month_name' => Carbon::create()->month($month)->format('F'),
                'summary' => $summary,
                'records' => $onboardingRecords,
            ]
        ]);
    }

    /**
     * Get monthly bank transfer report for an agent
     * Shows customer data with approved BT, pending BT, and reward pass status
     */
    public function getBankTransferMonthlyReport(Request $request)
    {
        $user = $request->user();

        // If the user is an agent, show their own data
        // If leader/admin, they can optionally specify an agent_id
        $agentId = $user->role === 'agent' ? $user->id : $request->input('agent_id', $user->id);

        // Validate year and month (optional filters)
        $year = $request->input('year', date('Y'));
        $month = $request->input('month', date('m'));

        // Get all bank transfers for the agent in the specified month
        $bankTransfers = BankTransfer::where('agent_id', $agentId)
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->get();

        // Group by customer mobile number
        $customerData = $bankTransfers->groupBy('customer_mobile')->map(function ($transfers) {
            $approvedBT = $transfers->where('status', 'approved')->sum('amount');
            $pendingBT = $transfers->where('status', 'pending')->count();
            
            $firstTransfer = $transfers->first();
            
            // Check if customer has active reward pass
            $rewardPass = RewardPass::where('customer_mobile', $firstTransfer->customer_mobile)
                ->where('status', 'approved')
                ->first();
            
            return [
                'customer_name' => $firstTransfer->customer_name,
                'mobile_no' => $firstTransfer->customer_mobile,
                'approved_bt' => (float) $approvedBT,
                'pending_bt' => $pendingBT,
                'reward_pass' => $rewardPass ? 'Active' : '-',
            ];
        })->values();

        // Add serial numbers
        $records = $customerData->map(function ($customer, $index) {
            return array_merge(['sn' => $index + 1], $customer);
        });

        // Get monthly summary
        $summary = [
            'total_customers' => $customerData->count(),
            'total_bank_transfers' => $bankTransfers->count(),
            'approved_transfers' => $bankTransfers->where('status', 'approved')->count(),
            'pending_transfers' => $bankTransfers->where('status', 'pending')->count(),
            'rejected_transfers' => $bankTransfers->where('status', 'rejected')->count(),
            'total_approved_amount' => (float) $bankTransfers->where('status', 'approved')->sum('amount'),
            'total_amount' => (float) $bankTransfers->sum('amount'),
            'active_reward_passes' => RewardPass::whereIn('customer_mobile', $customerData->pluck('mobile_no'))
                ->where('status', 'approved')
                ->count(),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Bank transfer monthly report retrieved successfully',
            'data' => [
                'agent_id' => $agentId,
                'year' => (int) $year,
                'month' => (int) $month,
                'month_name' => Carbon::create()->month($month)->format('F'),
                'summary' => $summary,
                'records' => $records,
            ]
        ]);
    }
}
