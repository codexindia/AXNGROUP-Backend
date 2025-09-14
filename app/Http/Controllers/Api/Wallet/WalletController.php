<?php

namespace App\Http\Controllers\Api\Wallet;

use App\Http\Controllers\Controller;
use App\Models\WalletTransaction;
use App\Models\Withdrawal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Services\PayoutService;

class WalletController extends Controller
{
    public function getBalance(Request $request)
    {
        $wallet = $request->user()->wallet;

        if (!$wallet) {
            return response()->json([
                'success' => false,
                'message' => 'Wallet not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'balance' => $wallet->balance,
                'wallet_id' => $wallet->id
            ]
        ]);
    }

    public function getTransactions(Request $request)
    {
        $wallet = $request->user()->wallet;

        if (!$wallet) {
            return response()->json([
                'success' => false,
                'message' => 'Wallet not found'
            ], 404);
        }

        $transactions = $wallet->transactions()
                              ->orderBy('created_at', 'desc')
                              ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $transactions
        ]);
    }

    public function requestWithdrawal(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()->first()
            ], 422);
        }

        $wallet = $request->user()->wallet;

        if (!$wallet) {
            return response()->json([
                'success' => false,
                'message' => 'Wallet not found'
            ], 404);
        }

        if ($wallet->balance < $request->amount) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient balance'
            ], 400);
        }

        DB::beginTransaction();
        try {
            // Create withdrawal request
            $withdrawal = Withdrawal::create([
                'user_id' => $request->user()->id,
                'amount' => $request->amount,
                'status' => 'pending'
            ]);

            // Debit from wallet
            $wallet->decrement('balance', $request->amount);

            // Record transaction
            WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'type' => 'debit',
                'amount' => $request->amount,
                'reference_type' => 'withdrawal',
                'reference_id' => $withdrawal->id,
                'remark' => 'Withdrawal request'
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Withdrawal request submitted successfully',
                'data' => $withdrawal
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to process withdrawal request'
            ], 500);
        }
    }

    public function getWithdrawals(Request $request)
    {
        $withdrawals = $request->user()->withdrawals()
                              ->orderBy('created_at', 'desc')
                              ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $withdrawals
        ]);
    }

    public function creditWallet(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:1',
            'reference_type' => 'required|string',
            'reference_id' => 'nullable|integer',
            'remark' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()->first()
            ], 422);
        }

        // Only  admins can credit wallets
        if (!in_array($request->user()->role, ['admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to credit wallet'
            ], 403);
        }

        $user = \App\Models\User::find($request->user_id);
        $wallet = $user->wallet;

        if (!$wallet) {
            return response()->json([
                'success' => false,
                'message' => 'Wallet not found'
            ], 404);
        }

        DB::beginTransaction();
        try {
            // Credit wallet
            $wallet->increment('balance', $request->amount);

            // Record transaction
            WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'type' => 'credit',
                'amount' => $request->amount,
                'reference_type' => $request->reference_type,
                'reference_id' => $request->reference_id,
                'remark' => $request->remark
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Wallet credited successfully',
                'data' => [
                    'new_balance' => $wallet->fresh()->balance
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to credit wallet'
            ], 500);
        }
    }

    /**
     * Get agent's payout history from wallet transactions
     */
    public function getPayoutHistory(Request $request)
    {
        if ($request->user()->role !== 'agent') {
            return response()->json([
                'success' => false,
                'message' => 'Only agents can view payout history'
            ], 403);
        }

        $type = $request->query('type'); // onboarding_payout or bank_transfer_payout
        $payoutHistory = PayoutService::getAgentPayoutHistory($request->user()->id, $type);

        // Group by month for better organization
        $groupedPayouts = $payoutHistory->groupBy(function ($transaction) {
            return $transaction->created_at->format('Y-m');
        });

        $summary = [
            'total_onboarding_payout' => $payoutHistory->where('reference_type', 'onboarding_payout')->sum('amount'),
            'total_bank_transfer_payout' => $payoutHistory->where('reference_type', 'bank_transfer_payout')->sum('amount'),
            'total_payout' => $payoutHistory->sum('amount'),
            'this_month_payout' => $payoutHistory->where('created_at', '>=', now()->startOfMonth())->sum('amount')
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $summary,
                'transactions' => $payoutHistory->take(50), // Latest 50 transactions
                'grouped_by_month' => $groupedPayouts->take(12) // Last 12 months
            ]
        ]);
    }
}