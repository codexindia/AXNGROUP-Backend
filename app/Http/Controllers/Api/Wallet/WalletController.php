<?php

namespace App\Http\Controllers\Api\Wallet;

use App\Http\Controllers\Controller;
use App\Models\WalletTransaction;
use App\Models\Withdrawal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

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
                'errors' => $validator->errors()
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
                'errors' => $validator->errors()
            ], 422);
        }

        // Only leaders and admins can credit wallets
        if (!in_array($request->user()->role, ['leader', 'admin'])) {
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
}