<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class PublicVerificationController extends Controller
{
    /**
     * Public API to verify agent/leader ID card via QR code
     * No authentication required - accessible to anyone
     *
     * @param string $uniqueId - Employee ID (e.g., AXN00001, VHN00002)
     * @return JsonResponse
     */
    public function verifyIdCard($uniqueId): JsonResponse
    {
        $user = User::with([
            'profile',
            'kycVerification'
        ])
        ->where('unique_id', $uniqueId)
        ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'ID card not found. Invalid employee ID.',
                'verified' => false
            ], 404);
        }

        // Only show ID cards for agents and leaders
        if (!in_array($user->role, ['agent', 'leader'])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid ID card type',
                'verified' => false
            ], 400);
        }

        // Get profile photo (Priority: KYC profile_photo > user_profiles agent_photo)
        $profilePhotoUrl = null;
        if ($user->kycVerification && $user->kycVerification->profile_photo) {
            $profilePhotoUrl = Storage::disk('public')->url($user->kycVerification->profile_photo);
        } elseif ($user->profile && $user->profile->agent_photo) {
            $profilePhotoUrl = Storage::disk('public')->url($user->profile->agent_photo);
        }

        // Get designation based on role
        $designation = match($user->role) {
            'agent' => 'FSE',
            'leader' => 'Team Leader',
            'admin' => 'Admin',
            default => ucfirst($user->role)
        };

        // Get KYC status
        $kycStatus = $user->kycVerification ? $user->kycVerification->kyc_status : 'not_submitted';
        $isKycVerified = $kycStatus === 'approved';

        // Get account status
        $isActive = !$user->is_blocked;

        // Get address (from profile if available, otherwise from KYC)
        $address = $user->profile?->address ?? null;

        // Get working city from KYC
        $workingCity = $user->kycVerification?->working_city ?? null;

        // Check if ID card is still valid
        $isCardValid = true;
        $cardExpired = false;
        $validUntil = $user->profile?->id_card_valid_until;
        
        if ($validUntil) {
            $isCardValid = $validUntil->isFuture() || $validUntil->isToday();
            $cardExpired = !$isCardValid;
        }

        // Overall verification status
        $isFullyVerified = $isActive && $isKycVerified && $isCardValid;

        // Prepare public response (limited information for security)
        $response = [
            'verified' => $isFullyVerified,
            'employee_id' => $user->unique_id,
            'name' => $user->name,
            'designation' => $designation,
            'mobile' => $user->mobile,
            'profile_photo_url' => $profilePhotoUrl,
            
            // Address information
            'address' => $address,
            'working_city' => $workingCity,
            
            // Dates
            'joining_date' => $user->profile?->joining_date?->format('d M Y'),
            'valid_until' => $validUntil?->format('d M Y'),
            
            // Verification statuses
            'status' => [
                'is_active' => $isActive,
                'is_kyc_verified' => $isKycVerified,
                'kyc_status' => $kycStatus,
                'is_card_valid' => $isCardValid,
                'card_expired' => $cardExpired,
            ],
            
            // Messages for display
            'messages' => $this->getVerificationMessages($isActive, $isKycVerified, $isCardValid),
            
            // Verification timestamp
            'verified_at' => now()->format('d M Y H:i:s'),
        ];

        return response()->json([
            'success' => true,
            'data' => $response
        ]);
    }

    /**
     * Generate user-friendly verification messages
     *
     * @param bool $isActive
     * @param bool $isKycVerified
     * @param bool $isCardValid
     * @return array
     */
    private function getVerificationMessages(bool $isActive, bool $isKycVerified, bool $isCardValid): array
    {
        $messages = [];

        if (!$isActive) {
            $messages[] = [
                'type' => 'error',
                'text' => '⚠️ This account is currently blocked/inactive'
            ];
        }

        if (!$isKycVerified) {
            $messages[] = [
                'type' => 'warning',
                'text' => '⚠️ KYC verification is pending or not approved'
            ];
        }

        if (!$isCardValid) {
            $messages[] = [
                'type' => 'error',
                'text' => '⚠️ This ID card has expired'
            ];
        }

        if ($isActive && $isKycVerified && $isCardValid) {
            $messages[] = [
                'type' => 'success',
                'text' => '✅ This is a valid and verified ID card'
            ];
        }

        return $messages;
    }
}
