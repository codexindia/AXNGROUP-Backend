<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Shop;
use App\Models\BankTransfer;
use App\Models\RewardPass;
use App\Models\User;
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

        // Get lifetime onboarding counts for this agent
        $lifetimeOnboardingApproved = Shop::where('agent_id', $agentId)
            ->where('status', 'approved')
            ->count();
        $lifetimeOnboardingPending = Shop::where('agent_id', $agentId)
            ->where('status', 'pending')
            ->count();

        // Get lifetime bank transfer for this agent
        $lifetimeBankTransferApproved = BankTransfer::where('agent_id', $agentId)
            ->where('status', 'approved')
            ->sum('amount');
        $lifetimeBankTransferPending = BankTransfer::where('agent_id', $agentId)
            ->where('status', 'pending')
            ->sum('amount');

        // Get lifetime reward pass for this agent
        $lifetimeRewardPassApproved = RewardPass::where('agent_id', $agentId)
            ->where('status', 'approved')
            ->count();
        $lifetimeRewardPassPending = RewardPass::where('agent_id', $agentId)
            ->where('status', 'pending')
            ->count();

        // Get monthly summary
        $summary = [
            'total_onboarding' => $totalOnboarding,
            'approved' => $approved,
            'pending' => $pending,
            'rejected' => $rejected,
            'total_qr_trx' => $totalQrTrx,
            'lifetime_onboarding_approved' => $lifetimeOnboardingApproved,
            'lifetime_onboarding_pending' => $lifetimeOnboardingPending,
            'lifetime_bank_transfer_approved' => (float) $lifetimeBankTransferApproved,
            'lifetime_bank_transfer_pending' => (float) $lifetimeBankTransferPending,
            'lifetime_reward_pass_approved' => $lifetimeRewardPassApproved,
            'lifetime_reward_pass_pending' => $lifetimeRewardPassPending,
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
            $pendingBT = 200000 - $approvedBT;
            
            $firstTransfer = $transfers->first();
            
            // Check if customer has active reward pass
            $rewardPass = RewardPass::where('customer_mobile', $firstTransfer->customer_mobile)
                ->where('status', 'approved')
                ->first();
            
            return [
                'customer_name' => $firstTransfer->customer_name,
                'mobile_no' => $firstTransfer->customer_mobile,
                'approved_bt' => (float) $approvedBT,
                'pending_bt' => (float) $pendingBT,
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

        // Get lifetime onboarding counts for this agent
        $lifetimeOnboardingApproved = Shop::where('agent_id', $agentId)
            ->where('status', 'approved')
            ->count();
        $lifetimeOnboardingPending = Shop::where('agent_id', $agentId)
            ->where('status', 'pending')
            ->count();

        // Get lifetime bank transfer for this agent
        $lifetimeBankTransferApproved = BankTransfer::where('agent_id', $agentId)
            ->where('status', 'approved')
            ->sum('amount');
        $lifetimeBankTransferPending = BankTransfer::where('agent_id', $agentId)
            ->where('status', 'pending')
            ->sum('amount');

        // Get lifetime reward pass for this agent
        $lifetimeRewardPassApproved = RewardPass::where('agent_id', $agentId)
            ->where('status', 'approved')
            ->count();
        $lifetimeRewardPassPending = RewardPass::where('agent_id', $agentId)
            ->where('status', 'pending')
            ->count();

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
            'lifetime_onboarding_approved' => $lifetimeOnboardingApproved,
            'lifetime_onboarding_pending' => $lifetimeOnboardingPending,
            'lifetime_bank_transfer_approved' => (float) $lifetimeBankTransferApproved,
            'lifetime_bank_transfer_pending' => (float) $lifetimeBankTransferPending,
            'lifetime_reward_pass_approved' => $lifetimeRewardPassApproved,
            'lifetime_reward_pass_pending' => $lifetimeRewardPassPending,
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

        // Get lifetime onboarding counts for this agent
        $lifetimeOnboardingApproved = Shop::where('agent_id', $agentId)
            ->where('status', 'approved')
            ->count();
        $lifetimeOnboardingPending = Shop::where('agent_id', $agentId)
            ->where('status', 'pending')
            ->count();

        // Get lifetime bank transfer for this agent
        $lifetimeBankTransferApproved = BankTransfer::where('agent_id', $agentId)
            ->where('status', 'approved')
            ->sum('amount');
        $lifetimeBankTransferPending = BankTransfer::where('agent_id', $agentId)
            ->where('status', 'pending')
            ->sum('amount');

        // Get lifetime reward pass for this agent
        $lifetimeRewardPassApproved = RewardPass::where('agent_id', $agentId)
            ->where('status', 'approved')
            ->count();
        $lifetimeRewardPassPending = RewardPass::where('agent_id', $agentId)
            ->where('status', 'pending')
            ->count();

        // Get daily summary
        $summary = [
            'total_onboarding' => $totalOnboarding,
            'approved' => $approved,
            'pending' => $pending,
            'rejected' => $rejected,
            'total_qr_trx' => $totalQrTrx,
            'lifetime_onboarding_approved' => $lifetimeOnboardingApproved,
            'lifetime_onboarding_pending' => $lifetimeOnboardingPending,
            'lifetime_bank_transfer_approved' => (float) $lifetimeBankTransferApproved,
            'lifetime_bank_transfer_pending' => (float) $lifetimeBankTransferPending,
            'lifetime_reward_pass_approved' => $lifetimeRewardPassApproved,
            'lifetime_reward_pass_pending' => $lifetimeRewardPassPending,
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
            
            $firstTransfer = $transfers->first();
            
            // Check if customer has active reward pass
            $rewardPass = RewardPass::where('customer_mobile', $firstTransfer->customer_mobile)
                ->where('status', 'approved')
                ->first();
            
            return [
                'customer_name' => $firstTransfer->customer_name,
                'mobile_no' => $firstTransfer->customer_mobile,
                'approved_bt' => (float) $approvedBT,
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

        // Get lifetime onboarding counts for this agent
        $lifetimeOnboardingApproved = Shop::where('agent_id', $agentId)
            ->where('status', 'approved')
            ->count();
        $lifetimeOnboardingPending = Shop::where('agent_id', $agentId)
            ->where('status', 'pending')
            ->count();

        // Get lifetime bank transfer for this agent
        $lifetimeBankTransferApproved = BankTransfer::where('agent_id', $agentId)
            ->where('status', 'approved')
            ->sum('amount');
        $lifetimeBankTransferPending = BankTransfer::where('agent_id', $agentId)
            ->where('status', 'pending')
            ->sum('amount');

        // Get lifetime reward pass for this agent
        $lifetimeRewardPassApproved = RewardPass::where('agent_id', $agentId)
            ->where('status', 'approved')
            ->count();
        $lifetimeRewardPassPending = RewardPass::where('agent_id', $agentId)
            ->where('status', 'pending')
            ->count();

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
            'lifetime_onboarding_approved' => $lifetimeOnboardingApproved,
            'lifetime_onboarding_pending' => $lifetimeOnboardingPending,
            'lifetime_bank_transfer_approved' => (float) $lifetimeBankTransferApproved,
            'lifetime_bank_transfer_pending' => (float) $lifetimeBankTransferPending,
            'lifetime_reward_pass_approved' => $lifetimeRewardPassApproved,
            'lifetime_reward_pass_pending' => $lifetimeRewardPassPending,
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

    /**
     * Get daily team report for a TL (Team Leader)
     * Shows all PSCs/agents under the TL with their daily statistics
     * Admins can optionally specify a leader_id to view any TL's report
     */
    public function getTLDailyReport(Request $request)
    {
        $user = $request->user();

        // Only leaders and admins can access this endpoint
        if (!in_array($user->role, ['leader', 'admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only team leaders and admins can access this endpoint',
            ], 403);
        }

        // If admin, they can specify leader_id, otherwise use authenticated leader's ID
        $leaderId = $user->role === 'admin' ? $request->input('leader_id') : $user->id;

        // If admin didn't specify leader_id, return error
        if ($user->role === 'admin' && !$leaderId) {
            return response()->json([
                'success' => false,
                'message' => 'Admin must specify leader_id parameter',
            ], 400);
        }

        // Get the leader details
        $leader = User::find($leaderId);
        if (!$leader || $leader->role !== 'leader') {
            return response()->json([
                'success' => false,
                'message' => 'Invalid leader ID',
            ], 404);
        }

        // Get date (default to today)
        $date = $request->input('date', date('Y-m-d'));

        // Get all agents under this leader
        $agents = $leader->agents()->get();

        if ($agents->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'No agents found under this team leader',
                'data' => [
                    'leader_id' => $leader->id,
                    'leader_name' => $leader->name,
                    'date' => $date,
                    'day_name' => Carbon::parse($date)->format('l'),
                    'summary' => [
                        'total_psc_count' => 0,
                        'total_bank_transfer_approved' => 0,
                        'total_bank_transfer_pending' => 0,
                        
                        'total_onboarding_approved' => 0,
                        'total_onboarding_pending' => 0,
                        'total_reward_pass_approved' => 0,
                        'total_reward_pass_pending' => 0,
                        'lifetime_bank_transfer_approved' => 0,
                        'lifetime_bank_transfer_pending' => 0,
                        'lifetime_onboarding_approved' => 0,
                        'lifetime_onboarding_pending' => 0,
                        'lifetime_reward_pass_approved' => 0,
                        'lifetime_reward_pass_pending' => 0,
                    ],
                    'psc_records' => []
                ]
            ]);
        }

        $agentIds = $agents->pluck('id')->toArray();

        // Initialize daily totals for approved
        $totalBankTransferApproved = 0;
        $totalBankChargeApproved = 0;
        $totalOnboardingApproved = 0;
        $totalRewardPassApproved = 0;

        // Initialize daily totals for pending
        $totalBankTransferPending = 0;
        $totalBankChargePending = 0;
        $totalOnboardingPending = 0;
        $totalRewardPassPending = 0;

        // Initialize lifetime totals for approved
        $lifetimeBankTransferApproved = 0;
        $lifetimeOnboardingApproved = 0;
        $lifetimeRewardPassApproved = 0;

        // Initialize lifetime totals for pending
        $lifetimeBankTransferPending = 0;
        $lifetimeOnboardingPending = 0;
        $lifetimeRewardPassPending = 0;

        // Build PSC records
        $pscRecords = $agents->map(function ($agent) use ($date, &$totalBankTransferApproved, &$totalBankChargeApproved, &$totalOnboardingApproved, &$totalRewardPassApproved, &$totalBankTransferPending, &$totalBankChargePending, &$totalOnboardingPending, &$totalRewardPassPending, &$lifetimeBankTransferApproved, &$lifetimeOnboardingApproved, &$lifetimeRewardPassApproved, &$lifetimeBankTransferPending, &$lifetimeOnboardingPending, &$lifetimeRewardPassPending) {
            // Get approved onboarding count for this agent on this date
            $onboardingApprovedCount = Shop::where('agent_id', $agent->id)
                ->whereDate('created_at', $date)
                ->where('status', 'approved')
                ->count();

            // Get pending onboarding count for this agent on this date
            $onboardingPendingCount = Shop::where('agent_id', $agent->id)
                ->whereDate('created_at', $date)
                ->where('status', 'pending')
                ->count();

            // Get approved bank transfers for this agent on this date
            $bankTransfersApproved = BankTransfer::where('agent_id', $agent->id)
                ->whereDate('created_at', $date)
                ->where('status', 'approved')
                ->get();

            $bankTransferApprovedAmount = $bankTransfersApproved->sum('amount');
            
            // Get pending bank transfers for this agent on this date
            $bankTransfersPending = BankTransfer::where('agent_id', $agent->id)
                ->whereDate('created_at', $date)
                ->where('status', 'pending')
                ->get();

            $bankTransferPendingAmount = $bankTransfersPending->sum('amount');
            
            // Calculate bank charge (1.5% of bank transfer amount)
            $bankChargeApproved = $bankTransferApprovedAmount * 0.015;
            $bankChargePending = $bankTransferPendingAmount * 0.015;

            // Get approved reward pass count for this agent on this date
            $rewardPassApprovedCount = RewardPass::where('agent_id', $agent->id)
                ->whereDate('created_at', $date)
                ->where('status', 'approved')
                ->count();

            // Get pending reward pass count for this agent on this date
            $rewardPassPendingCount = RewardPass::where('agent_id', $agent->id)
                ->whereDate('created_at', $date)
                ->where('status', 'pending')
                ->count();

            // Get lifetime approved onboarding count for this agent
            $lifetimeOnboardingApprovedCount = Shop::where('agent_id', $agent->id)
                ->where('status', 'approved')
                ->count();

            // Get lifetime pending onboarding count for this agent
            $lifetimeOnboardingPendingCount = Shop::where('agent_id', $agent->id)
                ->where('status', 'pending')
                ->count();

            // Get lifetime approved bank transfers for this agent
            $lifetimeBankTransferApprovedAmount = BankTransfer::where('agent_id', $agent->id)
                ->where('status', 'approved')
                ->sum('amount');

            // Get lifetime pending bank transfers for this agent
            $lifetimeBankTransferPendingAmount = BankTransfer::where('agent_id', $agent->id)
                ->where('status', 'pending')
                ->sum('amount');

            // Get lifetime approved reward pass count for this agent
            $lifetimeRewardPassApprovedCount = RewardPass::where('agent_id', $agent->id)
                ->where('status', 'approved')
                ->count();

            // Get lifetime pending reward pass count for this agent
            $lifetimeRewardPassPendingCount = RewardPass::where('agent_id', $agent->id)
                ->where('status', 'pending')
                ->count();

            // Add to daily approved totals
            $totalBankTransferApproved += $bankTransferApprovedAmount;
            $totalBankChargeApproved += $bankChargeApproved;
            $totalOnboardingApproved += $onboardingApprovedCount;
            $totalRewardPassApproved += $rewardPassApprovedCount;

            // Add to daily pending totals
            $totalBankTransferPending += $bankTransferPendingAmount;
            $totalBankChargePending += $bankChargePending;
            $totalOnboardingPending += $onboardingPendingCount;
            $totalRewardPassPending += $rewardPassPendingCount;

            // Add to lifetime approved totals
            $lifetimeBankTransferApproved += $lifetimeBankTransferApprovedAmount;
            $lifetimeOnboardingApproved += $lifetimeOnboardingApprovedCount;
            $lifetimeRewardPassApproved += $lifetimeRewardPassApprovedCount;

            // Add to lifetime pending totals
            $lifetimeBankTransferPending += $lifetimeBankTransferPendingAmount;
            $lifetimeOnboardingPending += $lifetimeOnboardingPendingCount;
            $lifetimeRewardPassPending += $lifetimeRewardPassPendingCount;

            return [
                'psc_name' => $agent->name,
                'psc_id' => $agent->unique_id ?? $agent->id,
                'psc_onboarding_approved' => $onboardingApprovedCount,
                'psc_onboarding_pending' => $onboardingPendingCount,
                'psc_onboarding_lifetime_approved' => $lifetimeOnboardingApprovedCount,
                'psc_onboarding_lifetime_pending' => $lifetimeOnboardingPendingCount,
                'bank_trn_approved' => (float) $bankTransferApprovedAmount,
                'bank_trn_pending' => (float) $bankTransferPendingAmount,
                'bank_trn_lifetime_approved' => (float) $lifetimeBankTransferApprovedAmount,
                'bank_trn_lifetime_pending' => (float) $lifetimeBankTransferPendingAmount,
                'bank_trn_fee_approved' => (float) $bankChargeApproved,
                'bank_trn_fee_pending' => (float) $bankChargePending,
                'reward_pass_approved' => $rewardPassApprovedCount,
                'reward_pass_pending' => $rewardPassPendingCount,
                'reward_pass_lifetime_approved' => $lifetimeRewardPassApprovedCount,
                'reward_pass_lifetime_pending' => $lifetimeRewardPassPendingCount,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'message' => 'TL daily report retrieved successfully',
            'data' => [
                'leader_id' => $leader->id,
                'leader_name' => $leader->name,
                'date' => $date,
                'day_name' => Carbon::parse($date)->format('l'),
                'summary' => [
                    'total_psc_count' => $agents->count(),
                    'total_bank_transfer_approved' => (float) $totalBankTransferApproved,
                    'total_bank_transfer_pending' => (float) $totalBankTransferPending,
                    // 'total_bank_charge_approved' => (float) $totalBankChargeApproved,
                    // 'total_bank_charge_pending' => (float) $totalBankChargePending,
                    'total_onboarding_approved' => $totalOnboardingApproved,
                    'total_onboarding_pending' => $totalOnboardingPending,
                    'total_reward_pass_approved' => $totalRewardPassApproved,
                    'total_reward_pass_pending' => $totalRewardPassPending,
                    'lifetime_bank_transfer_approved' => (float) $lifetimeBankTransferApproved,
                    'lifetime_bank_transfer_pending' => (float) $lifetimeBankTransferPending,
                    'lifetime_onboarding_approved' => $lifetimeOnboardingApproved,
                    'lifetime_onboarding_pending' => $lifetimeOnboardingPending,
                    'lifetime_reward_pass_approved' => $lifetimeRewardPassApproved,
                    'lifetime_reward_pass_pending' => $lifetimeRewardPassPending,
                ],
                'psc_records' => $pscRecords
            ]
        ]);
    }
}
