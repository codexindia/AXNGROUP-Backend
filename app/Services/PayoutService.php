<?php

namespace App\Services;

use App\Models\Shop;
use App\Models\BankTransfer;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Carbon\Carbon;

class PayoutService
{
    /**
     * Calculate onboarding payout based on slab (Tide QR Onboarding Payout)
     */
    public static function calculateOnboardingPayout($agentId)
    {
        // Count approved onboardings for current month
        $currentMonth = Carbon::now();
        $onboardingCount = Shop::where('agent_id', $agentId)
            ->where('status', 'approved')
            ->whereYear('updated_at', $currentMonth->year)
            ->whereMonth('updated_at', $currentMonth->month)
            ->count();

        // Slab-based payout calculation
        if ($onboardingCount >= 0 && $onboardingCount <= 20) {
            return 200;
        } elseif ($onboardingCount >= 21 && $onboardingCount <= 40) {
            return 240;
        } elseif ($onboardingCount >= 41) {
            return 250;
        }

        return 0;
    }

    /**
     * Calculate bank transfer payout based on slab
     */
    public static function calculateBankTransferPayout($totalAmount)
    {
        // Bank transfer payout slabs
        if ($totalAmount >= 200000) {
            return 450;
        } elseif ($totalAmount >= 150000) {
            return 330;
        } elseif ($totalAmount >= 100000) {
            return 200;
        } elseif ($totalAmount >= 50000) {
            return 120;
        }

        return 0; // Below ₹50,000 - no payout as per rules
    }

    /**
     * Get agent's total bank transfer amount for current month
     */
    public static function getAgentBankTransferTotal($agentId)
    {
        $currentMonth = Carbon::now();
        return BankTransfer::where('agent_id', $agentId)
            ->where('status', 'approved')
            ->whereYear('updated_at', $currentMonth->year)
            ->whereMonth('updated_at', $currentMonth->month)
            ->sum('amount');
    }

    /**
     * Credit payout to agent's wallet
     */
    public static function creditPayout($agentId, $amount, $type, $referenceId)
    {
        if ($amount <= 0) {
            return false;
        }

        // Check if payout already processed for this reference
        $existingTransaction = WalletTransaction::whereHas('wallet', function ($query) use ($agentId) {
                $query->where('user_id', $agentId);
            })
            ->where('reference_type', $type)
            ->where('reference_id', $referenceId)
            ->exists();

        if ($existingTransaction) {
            return false; // Already processed
        }

        // Get or create wallet for agent
        $wallet = Wallet::firstOrCreate(
            ['user_id' => $agentId],
            ['balance' => 0]
        );

        // Credit wallet
        $wallet->increment('balance', $amount);

        // Record transaction
        WalletTransaction::create([
            'wallet_id' => $wallet->id,
            'type' => 'credit',
            'amount' => $amount,
            'reference_type' => $type,
            'reference_id' => $referenceId,
            'remark' => $type === 'onboarding_payout' 
                ? "Onboarding payout commission - ₹{$amount}" 
                : "Bank transfer payout commission - ₹{$amount}"
        ]);

        return true;
    }

    /**
     * Check if bank transfer is eligible for payout
     */
    public static function isBankTransferEligible($amount)
    {
        return $amount >= 50000; // Minimum ₹50,000 as per rules
    }

    /**
     * Apply fee deduction for bank transfers below ₹50,000
     */
    public static function calculateFeeDeduction($amount)
    {
        if ($amount < 50000) {
            return $amount * 0.015; // 1.5% fee deduction
        }
        return 0;
    }

    /**
     * Get agent's payout history from wallet transactions
     */
    public static function getAgentPayoutHistory($agentId, $type = null)
    {
        $query = WalletTransaction::whereHas('wallet', function ($query) use ($agentId) {
                $query->where('user_id', $agentId);
            })
            ->where('type', 'credit')
            ->whereIn('reference_type', ['onboarding_payout', 'bank_transfer_payout']);

        if ($type) {
            $query->where('reference_type', $type);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }
}
