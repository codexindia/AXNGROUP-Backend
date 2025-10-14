<?php

namespace App\Http\Controllers\Api\Shop;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\User;
use App\Models\RewardPass;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Services\PayoutService;

class ShopController extends Controller
{
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_name' => 'required|string|max:255',
            'customer_mobile' => 'required|string|max:15|unique:shops,customer_mobile'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
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
            'customer_name' => $request->customer_name,
            'customer_mobile' => $request->customer_mobile,
            'status' => 'pending'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Shop onboarding request created successfully',
            'data' => $shop->load(['agent'])
        ], 201);
    }

    public function getByAgent(Request $request)
    {
        // Date filter using "between" if both dates are provided
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date')??date('Y-m-d');

        //return  \Carbon\Carbon::parse($startDate)->startOfDay().' '. \Carbon\Carbon::parse($endDate)->endOfDay();
               

      

        $shops = Shop::where('agent_id', $request->user()->id)
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $start = \Carbon\Carbon::parse($startDate)->startOfDay();
                $end   = \Carbon\Carbon::parse($endDate)->endOfDay();
                return $query->whereBetween('created_at', [$start, $end]);
            })
          
            ->orderBy('created_at', 'desc')
            ->with(['onboardingSheetData:phone,qr_trx,referral'])
            ->paginate(20)
            ->through(function ($shop) {
                $shop['qr_trx'] = $shop->onboardingSheetData ? $shop->onboardingSheetData->qr_trx : 0;
                $shop['s_referral'] = $shop->onboardingSheetData ? $shop->onboardingSheetData->referral : null;
                unset($shop->onboardingSheetData);
                return $shop;
            });

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

        // Get all agents under this leader first
        $agentIds = $request->user()->agents()->pluck('id');

        $shops = Shop::whereIn('agent_id', $agentIds)
            ->with(['agent:id,name'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $shops
        ]);
    }

    public function show($id)
    {
        $shop = Shop::with(['agent.parent'])->find($id);

        if (!$shop) {
            return response()->json([
                'success' => false,
                'message' => 'Shop not found'
            ], 404);
        }

        // Check if user has permission to view this shop
        $user = auth()->user();
        $isAgentOwner = $shop->agent_id === $user->id;
        $isTeamLeader = $user->role === 'leader' && $shop->agent->parent_id === $user->id;
        $isAdmin = in_array($user->role, ['admin']);

        if (!$isAgentOwner && !$isTeamLeader && !$isAdmin) {
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

    /**
     * Admin: Get onboarding history with filters
     */
    public function getOnboardingHistory(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Only admins can view onboarding history'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'nullable|in:pending,approved,rejected',
            'agent_id' => 'nullable|exists:users,id',
            'leader_id' => 'nullable|exists:users,id', // Changed from team_leader_id to leader_id
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'search' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        $query = Shop::with(['agent.parent'])->with(['onboardingSheetData']);

        // Apply filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('agent_id')) {
            $query->where('agent_id', $request->agent_id);
        }

        if ($request->filled('leader_id')) {
            // Filter shops by leader - get agents under this leader first
            $agentIds = User::where('parent_id', $request->leader_id)->pluck('id');
            $query->whereIn('agent_id', $agentIds);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_mobile', 'like', "%{$search}%")
                    ->orWhereHas('agent', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('agent.parent', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $perPage = $request->get('per_page', 20);
        $shops = $query->orderBy('created_at', 'desc')->paginate($perPage)->through(function ($shop) {
            // Use the correct relationship name (camelCase)
            $shop['qr_trx'] = $shop->onboardingSheetData ? $shop->onboardingSheetData->qr_trx : 0;
            $shop['s_referral'] = $shop->onboardingSheetData ? $shop->onboardingSheetData->referral : null;
            unset($shop->onboardingSheetData);
            return $shop;
        });

        // Get statistics
        $stats = [
            'total' => Shop::count(),
            'pending' => Shop::where('status', 'pending')->count(),
            'approved' => Shop::where('status', 'approved')->count(),
            'rejected' => Shop::where('status', 'rejected')->count(),
            'today' => Shop::whereDate('created_at', Carbon::today())->count(),
            'this_month' => Shop::whereMonth('created_at', Carbon::now()->month)->count()
        ];

        return response()->json([
            'success' => true,
            'data' => $shops,
            'statistics' => $stats
        ]);
    }

    /**
     * Admin: Get bank transfer history
     */
    public function getBankTransferHistory(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Only admins can view bank transfer history'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'nullable|in:pending,approved,rejected',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'min_amount' => 'nullable|numeric|min:0',
            'max_amount' => 'nullable|numeric|gt:min_amount',
            'search' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        // Use BankTransfer model instead of Shop
        $query = \App\Models\BankTransfer::with(['agent.parent']);

        // Apply filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->filled('min_amount')) {
            $query->where('amount', '>=', $request->min_amount);
        }

        if ($request->filled('max_amount')) {
            $query->where('amount', '<=', $request->max_amount);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_mobile', 'like', "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', 20);
        $transfers = $query->orderBy('created_at', 'desc')->paginate($perPage);

        // Calculate transfer statistics
        $stats = [
            'total_transfers' => \App\Models\BankTransfer::count(),
            'total_amount' => \App\Models\BankTransfer::sum('amount') ?? 0,
            'pending_transfers' => \App\Models\BankTransfer::where('status', 'pending')->count(),
            'approved_transfers' => \App\Models\BankTransfer::where('status', 'approved')->count(),
            'rejected_transfers' => \App\Models\BankTransfer::where('status', 'rejected')->count(),
            'today_transfers' => \App\Models\BankTransfer::whereDate('created_at', Carbon::today())->count(),
            'today_amount' => \App\Models\BankTransfer::whereDate('created_at', Carbon::today())->sum('amount') ?? 0,
            'this_month_amount' => \App\Models\BankTransfer::whereMonth('created_at', Carbon::now()->month)->sum('amount') ?? 0
        ];

        return response()->json([
            'success' => true,
            'data' => $transfers,
            'statistics' => $stats
        ]);
    }

    /**
     * Admin: Get daily reports
     */
    public function getDailyReports(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Only admins can view daily reports'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'date' => 'nullable|date',
            'days' => 'nullable|integer|min:1|max:365'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        $date = $request->filled('date') ? Carbon::parse($request->date) : Carbon::today();
        $days = $request->get('days', 30);

        // Get daily statistics for the specified period
        $dailyStats = [];
        for ($i = 0; $i < $days; $i++) {
            $currentDate = $date->copy()->subDays($i);

            $dayStats = [
                'date' => $currentDate->format('Y-m-d'),
                'day_name' => $currentDate->format('l'),
                'onboarding_created' => Shop::whereDate('created_at', $currentDate)->count(),
                'onboarding_approved' => Shop::whereDate('updated_at', $currentDate)
                    ->where('status', 'approved')
                    ->count(),
                'onboarding_rejected' => Shop::whereDate('updated_at', $currentDate)
                    ->where('status', 'rejected')
                    ->count(),
                'bank_transfers' => \App\Models\BankTransfer::whereDate('created_at', $currentDate)->count(),
                'transfer_amount' => \App\Models\BankTransfer::whereDate('created_at', $currentDate)->sum('amount') ?? 0,
                'reward_passes_created' => \App\Models\RewardPass::whereDate('created_at', $currentDate)->count(),
                'reward_passes_approved' => \App\Models\RewardPass::whereDate('updated_at', $currentDate)
                    ->where('status', 'approved')
                    ->count(),
                'reward_passes_rejected' => \App\Models\RewardPass::whereDate('updated_at', $currentDate)
                    ->where('status', 'rejected')
                    ->count()
            ];

            $dailyStats[] = $dayStats;
        }

        // Overall summary
        $summary = [
            'period' => [
                'from' => $date->copy()->subDays($days - 1)->format('Y-m-d'),
                'to' => $date->format('Y-m-d'),
                'days' => $days
            ],
            'totals' => [
                'onboarding_created' => array_sum(array_column($dailyStats, 'onboarding_created')),
                'onboarding_approved' => array_sum(array_column($dailyStats, 'onboarding_approved')),
                'onboarding_rejected' => array_sum(array_column($dailyStats, 'onboarding_rejected')),
                'bank_transfers' => array_sum(array_column($dailyStats, 'bank_transfers')),
                'total_transfer_amount' => array_sum(array_column($dailyStats, 'transfer_amount')),
                'reward_passes_created' => array_sum(array_column($dailyStats, 'reward_passes_created')),
                'reward_passes_approved' => array_sum(array_column($dailyStats, 'reward_passes_approved')),
                'reward_passes_rejected' => array_sum(array_column($dailyStats, 'reward_passes_rejected'))
            ],
            'averages' => [
                'daily_onboarding' => round(array_sum(array_column($dailyStats, 'onboarding_created')) / $days, 2),
                'daily_approvals' => round(array_sum(array_column($dailyStats, 'onboarding_approved')) / $days, 2),
                'daily_transfers' => round(array_sum(array_column($dailyStats, 'bank_transfers')) / $days, 2),
                'daily_transfer_amount' => round(array_sum(array_column($dailyStats, 'transfer_amount')) / $days, 2),
                'daily_reward_passes' => round(array_sum(array_column($dailyStats, 'reward_passes_created')) / $days, 2),
                'daily_reward_approvals' => round(array_sum(array_column($dailyStats, 'reward_passes_approved')) / $days, 2)
            ]
        ];

        // Top performing agents and leaders
        $topAgents = Shop::select('agent_id', DB::raw('count(*) as total_onboarding'))
            ->whereBetween('created_at', [
                $date->copy()->subDays($days - 1)->startOfDay(),
                $date->copy()->endOfDay()
            ])
            ->with('agent:id,name')
            ->groupBy('agent_id')
            ->orderBy('total_onboarding', 'desc')
            ->limit(10)
            ->get();

        $topLeaders = Shop::select('users.id as leader_id', 'users.name as leader_name', DB::raw('count(shops.id) as total_approved'))
            ->join('users as agents', 'shops.agent_id', '=', 'agents.id')
            ->join('users', 'agents.parent_id', '=', 'users.id')
            ->whereBetween('shops.updated_at', [
                $date->copy()->subDays($days - 1)->startOfDay(),
                $date->copy()->endOfDay()
            ])
            ->where('shops.status', 'approved')
            ->where('users.role', 'leader')
            ->groupBy('users.id', 'users.name')
            ->orderBy('total_approved', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'daily_statistics' => array_reverse($dailyStats), // Show oldest first
                'summary' => $summary,
                'top_performers' => [
                    'agents' => $topAgents,
                    'leaders' => $topLeaders
                ]
            ]
        ]);
    }

    /**
     * Admin approval for onboarding (triggers payout)
     */
    public function adminApproval(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:approved,rejected,pending',
            'admin_remark' => 'nullable|string'
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
                'message' => 'Only admin can approve/reject shops'
            ], 403);
        }

        $shop = Shop::find($id);

        if (!$shop) {
            return response()->json([
                'success' => false,
                'message' => 'Shop not found'
            ], 404);
        }

        // Shop must be pending
        if ($shop->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Shop has already been processed'
            ], 400);
        }

        $shop->update([
            'status' => $request->status,
            'reject_remark' => $request->status === 'rejected' ? $request->admin_remark : null
        ]);

        // If admin approved, calculate and credit payout
        if ($request->status === 'approved') {
            $payoutAmount = PayoutService::calculateOnboardingPayout($shop->agent_id);

            if ($payoutAmount > 0) {
                PayoutService::creditPayout(
                    $shop->agent_id,
                    $payoutAmount,
                    'onboarding_payout',
                    $shop->id
                );
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Shop approval updated successfully',
            'data' => $shop->load(['agent.parent'])
        ]);
    }

    /**
     * Get pending shops for admin approval
     */
    public function getPendingForAdmin()
    {
        $shops = Shop::where('status', 'pending') // Direct pending, no leader approval needed
            ->with(['agent.parent'])
            ->orderBy('created_at', 'asc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $shops
        ]);
    }

    /**
     * Get agent statistics - total counts and amounts
     */
    public function getAgentStatistics(Request $request)
    {
        // Only agents can view their own statistics
        if ($request->user()->role !== 'agent') {
            return response()->json([
                'success' => false,
                'message' => 'Only agents can view their statistics'
            ], 403);
        }

        $agentId = $request->user()->id;

        // Shop onboarding statistics
        $totalShops = Shop::where('agent_id', $agentId)->count();
        $approvedShops = Shop::where('agent_id', $agentId)->where('status', 'approved')->count();
        $pendingShops = Shop::where('agent_id', $agentId)->where('status', 'pending')->count();
        $rejectedShops = Shop::where('agent_id', $agentId)->where('status', 'rejected')->count();

        // Bank transfer statistics
        $totalBankTransfers = \App\Models\BankTransfer::where('agent_id', $agentId)->count();
        $approvedBankTransfers = \App\Models\BankTransfer::where('agent_id', $agentId)->where('status', 'approved')->count();
        $pendingBankTransfers = \App\Models\BankTransfer::where('agent_id', $agentId)->where('status', 'pending')->count();
        $rejectedBankTransfers = \App\Models\BankTransfer::where('agent_id', $agentId)->where('status', 'rejected')->count();
        $totalBankTransferAmount = \App\Models\BankTransfer::where('agent_id', $agentId)->where('status', 'approved')->sum('amount') ?? 0;

        // Reward pass statistics
        $totalRewardPasses = \App\Models\RewardPass::where('agent_id', $agentId)->count();
        $approvedRewardPasses = \App\Models\RewardPass::where('agent_id', $agentId)->where('status', 'approved')->count();
        $pendingRewardPasses = \App\Models\RewardPass::where('agent_id', $agentId)->where('status', 'pending')->count();
        $rejectedRewardPasses = \App\Models\RewardPass::where('agent_id', $agentId)->where('status', 'rejected')->count();

        // Monthly statistics (current month)
        $currentMonth = Carbon::now();
        $thisMonthShops = Shop::where('agent_id', $agentId)
            ->whereMonth('created_at', $currentMonth->month)
            ->whereYear('created_at', $currentMonth->year)
            ->count();

        $thisMonthBankTransfers = \App\Models\BankTransfer::where('agent_id', $agentId)
            ->whereMonth('created_at', $currentMonth->month)
            ->whereYear('created_at', $currentMonth->year)
            ->count();

        $thisMonthBankTransferAmount = \App\Models\BankTransfer::where('agent_id', $agentId)
            ->where('status', 'approved')
            ->whereMonth('updated_at', $currentMonth->month)
            ->whereYear('updated_at', $currentMonth->year)
            ->sum('amount') ?? 0;

        $thisMonthRewardPasses = \App\Models\RewardPass::where('agent_id', $agentId)
            ->whereMonth('created_at', $currentMonth->month)
            ->whereYear('created_at', $currentMonth->year)
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'agent_info' => [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'leader' => $request->user()->parent ? [
                        'id' => $request->user()->parent->id,
                        'name' => $request->user()->parent->name
                    ] : null
                ],
                'shop_onboarding' => [
                    'total' => $totalShops,
                    'approved' => $approvedShops,
                    'pending' => $pendingShops,
                    'rejected' => $rejectedShops,
                    'this_month' => $thisMonthShops
                ],
                'bank_transfers' => [
                    'total' => $totalBankTransfers,
                    'approved' => $approvedBankTransfers,
                    'pending' => $pendingBankTransfers,
                    'rejected' => $rejectedBankTransfers,
                    'total_amount' => $totalBankTransferAmount,
                    'this_month' => $thisMonthBankTransfers,
                    'this_month_amount' => $thisMonthBankTransferAmount,

                ],
                'reward_passes' => [
                    'total' => $totalRewardPasses,
                    'approved' => $approvedRewardPasses,
                    'pending' => $pendingRewardPasses,
                    'rejected' => $rejectedRewardPasses,
                    'this_month' => $thisMonthRewardPasses
                ],
                'summary' => [
                    'total_activities' => $totalShops + $totalBankTransfers + $totalRewardPasses,
                    'total_approved' => $approvedShops + $approvedBankTransfers + $approvedRewardPasses,
                    'total_pending' => $pendingShops + $pendingBankTransfers + $pendingRewardPasses,
                    'total_rejected' => $rejectedShops + $rejectedBankTransfers + $rejectedRewardPasses,
                    'success_rate' => $totalShops + $totalBankTransfers + $totalRewardPasses > 0
                        ? round((($approvedShops + $approvedBankTransfers + $approvedRewardPasses) / ($totalShops + $totalBankTransfers + $totalRewardPasses)) * 100, 2)
                        : 0
                ]
            ]
        ]);
    }

    /**
     * Get leader statistics - aggregated data from all assigned agents
     */
    public function getLeaderStatistics(Request $request)
    {
        // Only leaders can view their team statistics
        if ($request->user()->role !== 'leader') {
            return response()->json([
                'success' => false,
                'message' => 'Only leaders can view team statistics'
            ], 403);
        }

        $leaderId = $request->user()->id;

        // Get all agents under this leader
        $agentIds = $request->user()->agents()->pluck('id');
        $totalAgents = $agentIds->count();

        if ($totalAgents === 0) {
            return response()->json([
                'success' => true,
                'data' => [
                    'leader_info' => [
                        'id' => $request->user()->id,
                        'name' => $request->user()->name,
                        'email' => $request->user()->email,
                        'total_agents' => 0
                    ],
                    'shop_onboarding' => [
                        'total' => 0,
                        'approved' => 0,
                        'pending' => 0,
                        'rejected' => 0,
                        'this_month' => 0
                    ],
                    'bank_transfers' => [
                        'total' => 0,
                        'approved' => 0,
                        'pending' => 0,
                        'rejected' => 0,
                        'total_amount' => 0,
                        'this_month' => 0,
                        'this_month_amount' => 0
                    ],
                    'reward_passes' => [
                        'total' => 0,
                        'approved' => 0,
                        'pending' => 0,
                        'rejected' => 0,
                        'this_month' => 0
                    ],
                    'summary' => [
                        'total_activities' => 0,
                        'total_approved' => 0,
                        'total_pending' => 0,
                        'total_rejected' => 0,
                        'success_rate' => 0
                    ],
                    'agent_performance' => []
                ]
            ]);
        }

        // Shop onboarding statistics for all agents
        $totalShops = Shop::whereIn('agent_id', $agentIds)->count();
        $approvedShops = Shop::whereIn('agent_id', $agentIds)->where('status', 'approved')->count();
        $pendingShops = Shop::whereIn('agent_id', $agentIds)->where('status', 'pending')->count();
        $rejectedShops = Shop::whereIn('agent_id', $agentIds)->where('status', 'rejected')->count();

        // Bank transfer statistics for all agents
        $totalBankTransfers = \App\Models\BankTransfer::whereIn('agent_id', $agentIds)->count();
        $approvedBankTransfers = \App\Models\BankTransfer::whereIn('agent_id', $agentIds)->where('status', 'approved')->count();
        $pendingBankTransfers = \App\Models\BankTransfer::whereIn('agent_id', $agentIds)->where('status', 'pending')->count();
        $rejectedBankTransfers = \App\Models\BankTransfer::whereIn('agent_id', $agentIds)->where('status', 'rejected')->count();
        $totalBankTransferAmount = \App\Models\BankTransfer::whereIn('agent_id', $agentIds)->where('status', 'approved')->sum('amount') ?? 0;

        // Reward pass statistics for all agents
        $totalRewardPasses = \App\Models\RewardPass::whereIn('agent_id', $agentIds)->count();
        $approvedRewardPasses = \App\Models\RewardPass::whereIn('agent_id', $agentIds)->where('status', 'approved')->count();
        $pendingRewardPasses = \App\Models\RewardPass::whereIn('agent_id', $agentIds)->where('status', 'pending')->count();
        $rejectedRewardPasses = \App\Models\RewardPass::whereIn('agent_id', $agentIds)->where('status', 'rejected')->count();

        // Monthly statistics (current month)
        $currentMonth = Carbon::now();
        $thisMonthShops = Shop::whereIn('agent_id', $agentIds)
            ->whereMonth('created_at', $currentMonth->month)
            ->whereYear('created_at', $currentMonth->year)
            ->count();

        $thisMonthBankTransfers = \App\Models\BankTransfer::whereIn('agent_id', $agentIds)
            ->whereMonth('created_at', $currentMonth->month)
            ->whereYear('created_at', $currentMonth->year)
            ->count();

        $thisMonthBankTransferAmount = \App\Models\BankTransfer::whereIn('agent_id', $agentIds)
            ->where('status', 'approved')
            ->whereMonth('updated_at', $currentMonth->month)
            ->whereYear('updated_at', $currentMonth->year)
            ->sum('amount') ?? 0;

        $thisMonthRewardPasses = \App\Models\RewardPass::whereIn('agent_id', $agentIds)
            ->whereMonth('created_at', $currentMonth->month)
            ->whereYear('created_at', $currentMonth->year)
            ->count();

        // Individual agent performance
        $agentPerformance = [];
        foreach ($agentIds as $agentId) {
            $agent = User::find($agentId);

            $agentShops = Shop::where('agent_id', $agentId)->count();
            $agentApprovedShops = Shop::where('agent_id', $agentId)->where('status', 'approved')->count();

            $agentBankTransfers = \App\Models\BankTransfer::where('agent_id', $agentId)->count();
            $agentApprovedBankTransfers = \App\Models\BankTransfer::where('agent_id', $agentId)->where('status', 'approved')->count();
            $agentBankTransferAmount = \App\Models\BankTransfer::where('agent_id', $agentId)->where('status', 'approved')->sum('amount') ?? 0;

            $agentRewardPasses = \App\Models\RewardPass::where('agent_id', $agentId)->count();
            $agentApprovedRewardPasses = \App\Models\RewardPass::where('agent_id', $agentId)->where('status', 'approved')->count();

            $totalActivities = $agentShops + $agentBankTransfers + $agentRewardPasses;
            $totalApproved = $agentApprovedShops + $agentApprovedBankTransfers + $agentApprovedRewardPasses;

            $agentPerformance[] = [
                'agent_id' => $agentId,
                'agent_name' => $agent->name,
                'shops' => [
                    'total' => $agentShops,
                    'approved' => $agentApprovedShops
                ],
                'bank_transfers' => [
                    'total' => $agentBankTransfers,
                    'approved' => $agentApprovedBankTransfers,
                    'amount' => $agentBankTransferAmount
                ],
                'reward_passes' => [
                    'total' => $agentRewardPasses,
                    'approved' => $agentApprovedRewardPasses
                ],
                'total_activities' => $totalActivities,
                'total_approved' => $totalApproved,
                'success_rate' => $totalActivities > 0 ? round(($totalApproved / $totalActivities) * 100, 2) : 0
            ];
        }

        // Sort agents by total activities (most active first)
        usort($agentPerformance, function ($a, $b) {
            return $b['total_activities'] <=> $a['total_activities'];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'leader_info' => [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'total_agents' => $totalAgents
                ],
                'shop_onboarding' => [
                    'total' => $totalShops,
                    'approved' => $approvedShops,
                    'pending' => $pendingShops,
                    'rejected' => $rejectedShops,
                    'this_month' => $thisMonthShops
                ],
                'bank_transfers' => [
                    'total' => $totalBankTransfers,
                    'approved' => $approvedBankTransfers,
                    'pending' => $pendingBankTransfers,
                    'rejected' => $rejectedBankTransfers,
                    'total_amount' => $totalBankTransferAmount,
                    'this_month' => $thisMonthBankTransfers,
                    'this_month_amount' => $thisMonthBankTransferAmount
                ],
                'reward_passes' => [
                    'total' => $totalRewardPasses,
                    'approved' => $approvedRewardPasses,
                    'pending' => $pendingRewardPasses,
                    'rejected' => $rejectedRewardPasses,
                    'this_month' => $thisMonthRewardPasses
                ],
                'summary' => [
                    'total_activities' => $totalShops + $totalBankTransfers + $totalRewardPasses,
                    'total_approved' => $approvedShops + $approvedBankTransfers + $approvedRewardPasses,
                    'total_pending' => $pendingShops + $pendingBankTransfers + $pendingRewardPasses,
                    'total_rejected' => $rejectedShops + $rejectedBankTransfers + $rejectedRewardPasses,
                    'success_rate' => ($totalShops + $totalBankTransfers + $totalRewardPasses) > 0
                        ? round((($approvedShops + $approvedBankTransfers + $approvedRewardPasses) / ($totalShops + $totalBankTransfers + $totalRewardPasses)) * 100, 2)
                        : 0
                ],
                'agent_performance' => $agentPerformance
            ]
        ]);
    }
}
