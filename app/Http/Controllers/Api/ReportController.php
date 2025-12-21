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
        $perPage = $request->input('per_page', 15);

        // Build query for onboarding data
        $baseQuery = Shop::where('agent_id', $agentId)
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month);

        // Get summary statistics (before pagination)
        $totalOnboarding = $baseQuery->count();
        $approved = (clone $baseQuery)->where('status', 'approved')->count();
        $pending = (clone $baseQuery)->where('status', 'pending')->count();
        $rejected = (clone $baseQuery)->where('status', 'rejected')->count();
        
        // Get total QR transactions
        $totalQrTrx = (clone $baseQuery)
            ->with('onboardingSheetData')
            ->get()
            ->sum(function($shop) {
                return $shop->onboardingSheetData->qr_trx ?? 0;
            });

        // Get paginated records
        $paginatedShops = $baseQuery
            ->with('onboardingSheetData')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        // Transform paginated data
        $onboardingRecords = $paginatedShops->getCollection()->map(function ($shop, $index) use ($paginatedShops) {
            $currentPage = $paginatedShops->currentPage();
            $perPage = $paginatedShops->perPage();
            $sn = ($currentPage - 1) * $perPage + $index + 1;
            
            return [
                'sn' => $sn,
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
            'total_onboarding' => $totalOnboarding,
            'approved' => $approved,
            'pending' => $pending,
            'rejected' => $rejected,
            'total_qr_trx' => $totalQrTrx,
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
                'pagination' => [
                    'current_page' => $paginatedShops->currentPage(),
                    'per_page' => $paginatedShops->perPage(),
                    'total' => $paginatedShops->total(),
                    'last_page' => $paginatedShops->lastPage(),
                    'from' => $paginatedShops->firstItem(),
                    'to' => $paginatedShops->lastItem(),
                ]
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
        $perPage = $request->input('per_page', 15);
        $page = $request->input('page', 1);

        // Get all bank transfers for the agent in the specified month (for summary)
        $allBankTransfers = BankTransfer::where('agent_id', $agentId)
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->get();

        // Group by customer mobile number
        $allCustomerData = $allBankTransfers->groupBy('customer_mobile')->map(function ($transfers) {
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
                'latest_date' => $transfers->max('created_at'),
            ];
        })->values()->sortByDesc('latest_date')->values();

        // Manual pagination
        $total = $allCustomerData->count();
        $lastPage = ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;
        $paginatedCustomers = $allCustomerData->slice($offset, $perPage)->values();

        // Add serial numbers
        $records = $paginatedCustomers->map(function ($customer, $index) use ($page, $perPage) {
            $sn = ($page - 1) * $perPage + $index + 1;
            unset($customer['latest_date']); // Remove sorting field
            return array_merge(['sn' => $sn], $customer);
        });

        // Get monthly summary
        $summary = [
            'total_customers' => $allCustomerData->count(),
            'total_bank_transfers' => $allBankTransfers->count(),
            'approved_transfers' => $allBankTransfers->where('status', 'approved')->count(),
            'pending_transfers' => $allBankTransfers->where('status', 'pending')->count(),
            'rejected_transfers' => $allBankTransfers->where('status', 'rejected')->count(),
            'total_approved_amount' => (float) $allBankTransfers->where('status', 'approved')->sum('amount'),
            'total_amount' => (float) $allBankTransfers->sum('amount'),
            'active_reward_passes' => RewardPass::whereIn('customer_mobile', $allCustomerData->pluck('mobile_no'))
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
                'pagination' => [
                    'current_page' => (int) $page,
                    'per_page' => (int) $perPage,
                    'total' => $total,
                    'last_page' => (int) $lastPage,
                    'from' => $offset + 1,
                    'to' => min($offset + $perPage, $total),
                ]
            ]
        ]);
    }

    /**
     * Get daily onboarding report for an agent
     * Shows customer data with refer code and qr transactions for a specific date
     */
    public function getOnboardingDailyReport(Request $request)
    {
        $user = $request->user();

        // If the user is an agent, show their own data
        // If leader/admin, they can optionally specify an agent_id
        $agentId = $user->role === 'agent' ? $user->id : $request->input('agent_id', $user->id);

        // Get date (default to today)
        $date = $request->input('date', date('Y-m-d'));
        $perPage = $request->input('per_page', 15);

        // Build query for onboarding data
        $baseQuery = Shop::where('agent_id', $agentId)
            ->whereDate('created_at', $date);

        // Get summary statistics (before pagination)
        $totalOnboarding = $baseQuery->count();
        $approved = (clone $baseQuery)->where('status', 'approved')->count();
        $pending = (clone $baseQuery)->where('status', 'pending')->count();
        $rejected = (clone $baseQuery)->where('status', 'rejected')->count();
        
        // Get total QR transactions
        $totalQrTrx = (clone $baseQuery)
            ->with('onboardingSheetData')
            ->get()
            ->sum(function($shop) {
                return $shop->onboardingSheetData->qr_trx ?? 0;
            });

        // Get paginated records
        $paginatedShops = $baseQuery
            ->with('onboardingSheetData')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        // Transform paginated data
        $onboardingRecords = $paginatedShops->getCollection()->map(function ($shop, $index) use ($paginatedShops) {
            $currentPage = $paginatedShops->currentPage();
            $perPage = $paginatedShops->perPage();
            $sn = ($currentPage - 1) * $perPage + $index + 1;
            
            return [
                'sn' => $sn,
                'customer_name' => $shop->customer_name,
                'mobile_no' => $shop->customer_mobile,
                'refer_code' => $shop->onboardingSheetData->refer_code ?? 'N/A',
                'qr_trx' => $shop->onboardingSheetData->qr_trx ?? 0,
                'status' => $shop->status,
                'created_at' => $shop->created_at->format('Y-m-d H:i:s'),
            ];
        });

        // Get daily summary
        $summary = [
            'total_onboarding' => $totalOnboarding,
            'approved' => $approved,
            'pending' => $pending,
            'rejected' => $rejected,
            'total_qr_trx' => $totalQrTrx,
        ];

        return response()->json([
            'success' => true,
            'message' => 'Onboarding daily report retrieved successfully',
            'data' => [
                'agent_id' => $agentId,
                'date' => $date,
                'day_name' => Carbon::parse($date)->format('l'),
                'summary' => $summary,
                'records' => $onboardingRecords,
                'pagination' => [
                    'current_page' => $paginatedShops->currentPage(),
                    'per_page' => $paginatedShops->perPage(),
                    'total' => $paginatedShops->total(),
                    'last_page' => $paginatedShops->lastPage(),
                    'from' => $paginatedShops->firstItem(),
                    'to' => $paginatedShops->lastItem(),
                ]
            ]
        ]);
    }

    /**
     * Get daily bank transfer report for an agent
     * Shows customer data with approved BT, pending BT, and reward pass status for a specific date
     */
    public function getBankTransferDailyReport(Request $request)
    {
        $user = $request->user();

        // If the user is an agent, show their own data
        // If leader/admin, they can optionally specify an agent_id
        $agentId = $user->role === 'agent' ? $user->id : $request->input('agent_id', $user->id);

        // Get date (default to today)
        $date = $request->input('date', date('Y-m-d'));
        $perPage = $request->input('per_page', 15);
        $page = $request->input('page', 1);

        // Get all bank transfers for the agent on the specified date
        $allBankTransfers = BankTransfer::where('agent_id', $agentId)
            ->whereDate('created_at', $date)
            ->get();

        // Group by customer mobile number
        $allCustomerData = $allBankTransfers->groupBy('customer_mobile')->map(function ($transfers) {
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
                'latest_time' => $transfers->max('created_at'),
            ];
        })->values()->sortByDesc('latest_time')->values();

        // Manual pagination
        $total = $allCustomerData->count();
        $lastPage = ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;
        $paginatedCustomers = $allCustomerData->slice($offset, $perPage)->values();

        // Add serial numbers
        $records = $paginatedCustomers->map(function ($customer, $index) use ($page, $perPage) {
            $sn = ($page - 1) * $perPage + $index + 1;
            unset($customer['latest_time']); // Remove sorting field
            return array_merge(['sn' => $sn], $customer);
        });

        // Get daily summary
        $summary = [
            'total_customers' => $allCustomerData->count(),
            'total_bank_transfers' => $allBankTransfers->count(),
            'approved_transfers' => $allBankTransfers->where('status', 'approved')->count(),
            'pending_transfers' => $allBankTransfers->where('status', 'pending')->count(),
            'rejected_transfers' => $allBankTransfers->where('status', 'rejected')->count(),
            'total_approved_amount' => (float) $allBankTransfers->where('status', 'approved')->sum('amount'),
            'total_amount' => (float) $allBankTransfers->sum('amount'),
            'active_reward_passes' => RewardPass::whereIn('customer_mobile', $allCustomerData->pluck('mobile_no'))
                ->where('status', 'approved')
                ->count(),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Bank transfer daily report retrieved successfully',
            'data' => [
                'agent_id' => $agentId,
                'date' => $date,
                'day_name' => Carbon::parse($date)->format('l'),
                'summary' => $summary,
                'records' => $records,
                'pagination' => [
                    'current_page' => (int) $page,
                    'per_page' => (int) $perPage,
                    'total' => $total,
                    'last_page' => (int) $lastPage,
                    'from' => $total > 0 ? $offset + 1 : 0,
                    'to' => min($offset + $perPage, $total),
                ]
            ]
        ]);
    }
}
