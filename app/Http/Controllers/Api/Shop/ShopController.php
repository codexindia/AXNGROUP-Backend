<?php

namespace App\Http\Controllers\Api\Shop;

use App\Http\Controllers\Controller;
use App\Models\Shop;
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
            'team_leader_id' => 'nullable|exists:users,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'search' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $query = Shop::with(['agent', 'teamLeader']);

        // Apply filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('agent_id')) {
            $query->where('agent_id', $request->agent_id);
        }

        if ($request->filled('team_leader_id')) {
            $query->where('team_leader_id', $request->team_leader_id);
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
                  ->orWhereHas('teamLeader', function ($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $perPage = $request->get('per_page', 20);
        $shops = $query->orderBy('created_at', 'desc')->paginate($perPage);

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
            'status' => 'nullable|in:pending,completed,failed',
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
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Assuming we have a bank_transfers table related to shops
        $query = Shop::with(['agent', 'teamLeader'])
                    ->where('status', 'approved')
                    ->whereNotNull('bank_transfer_amount');

        // Apply filters
        if ($request->filled('date_from')) {
            $query->whereDate('bank_transfer_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('bank_transfer_date', '<=', $request->date_to);
        }

        if ($request->filled('min_amount')) {
            $query->where('bank_transfer_amount', '>=', $request->min_amount);
        }

        if ($request->filled('max_amount')) {
            $query->where('bank_transfer_amount', '<=', $request->max_amount);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                  ->orWhere('customer_mobile', 'like', "%{$search}%")
                  ->orWhere('bank_reference_number', 'like', "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', 20);
        $transfers = $query->orderBy('bank_transfer_date', 'desc')->paginate($perPage);

        // Calculate transfer statistics
        $stats = [
            'total_transfers' => Shop::where('status', 'approved')->whereNotNull('bank_transfer_amount')->count(),
            'total_amount' => Shop::where('status', 'approved')->sum('bank_transfer_amount') ?? 0,
            'today_transfers' => Shop::where('status', 'approved')
                                   ->whereDate('bank_transfer_date', Carbon::today())
                                   ->count(),
            'today_amount' => Shop::where('status', 'approved')
                                 ->whereDate('bank_transfer_date', Carbon::today())
                                 ->sum('bank_transfer_amount') ?? 0,
            'this_month_amount' => Shop::where('status', 'approved')
                                      ->whereMonth('bank_transfer_date', Carbon::now()->month)
                                      ->sum('bank_transfer_amount') ?? 0
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
                'message' => 'Validation error',
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
                'bank_transfers' => Shop::whereDate('bank_transfer_date', $currentDate)
                                       ->whereNotNull('bank_transfer_amount')
                                       ->count(),
                'transfer_amount' => Shop::whereDate('bank_transfer_date', $currentDate)
                                        ->sum('bank_transfer_amount') ?? 0
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
                'total_transfer_amount' => array_sum(array_column($dailyStats, 'transfer_amount'))
            ],
            'averages' => [
                'daily_onboarding' => round(array_sum(array_column($dailyStats, 'onboarding_created')) / $days, 2),
                'daily_approvals' => round(array_sum(array_column($dailyStats, 'onboarding_approved')) / $days, 2),
                'daily_transfers' => round(array_sum(array_column($dailyStats, 'bank_transfers')) / $days, 2),
                'daily_transfer_amount' => round(array_sum(array_column($dailyStats, 'transfer_amount')) / $days, 2)
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

        $topLeaders = Shop::select('team_leader_id', DB::raw('count(*) as total_approved'))
                         ->whereBetween('updated_at', [
                             $date->copy()->subDays($days - 1)->startOfDay(),
                             $date->copy()->endOfDay()
                         ])
                         ->where('status', 'approved')
                         ->with('teamLeader:id,name')
                         ->groupBy('team_leader_id')
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
     * Admin final approval for onboarding (triggers payout)
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

        $shop = Shop::find($id);

        if (!$shop) {
            return response()->json([
                'success' => false,
                'message' => 'Shop not found'
            ], 404);
        }

        // Shop must be approved by leader first
        if ($shop->status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Shop must be approved by team leader first'
            ], 400);
        }

        $shop->update([
            'status' => $request->status
        ]);

        // If admin approved, calculate and credit payout
        if ($request->status === 'admin_approved') {
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
            'message' => 'Shop admin approval updated successfully',
            'data' => $shop->load(['agent', 'teamLeader'])
        ]);
    }

    /**
     * Get pending shops for admin approval
     */
    public function getPendingForAdmin()
    {
        $shops = Shop::where('status', 'approved') // Leader approved, waiting for admin
                     ->with(['agent', 'teamLeader'])
                     ->orderBy('updated_at', 'asc')
                     ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $shops
        ]);
    }
}