<?php

namespace App\Http\Controllers\Api\Kyc;

use App\Http\Controllers\Controller;
use App\Models\KycVerification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class KycController extends Controller
{
    /**
     * Submit KYC documents (Agent/Leader)
     */
    public function submitKyc(Request $request)
    {
        // Only agents and leaders can submit KYC
        if (!in_array($request->user()->role, ['agent', 'leader'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only agents and leaders can submit KYC documents'
            ], 403);
        }

        // Check if KYC already exists
        $existingKyc = KycVerification::where('user_id', $request->user()->id)->first();
        
        if ($existingKyc && $existingKyc->kyc_status === 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'KYC is already approved'
            ], 400);
        }

        if ($existingKyc && $existingKyc->kyc_status === 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'KYC is already submitted and under review'
            ], 400);
        }

        $validator = Validator::make($request->all(), KycVerification::validationRules());

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()->first()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Store uploaded files
            $aadharPhoto = $this->storeFile($request->file('aadhar_photo'), 'kyc/aadhar');
            $aadharBackPhoto = $this->storeFile($request->file('aadhar_back_photo'), 'kyc/aadhar');
            $panPhoto = $this->storeFile($request->file('pan_photo'), 'kyc/pan');
            $passbookPhoto = $this->storeFile($request->file('passbook_photo'), 'kyc/passbook');
            $profilePhoto = $this->storeFile($request->file('profile_photo'), 'kyc/profile');

            // Create or update KYC record
            $kycData = [
                'user_id' => $request->user()->id,
                'aadhar_number' => $request->aadhar_number,
                'aadhar_photo' => $aadharPhoto,
                'aadhar_back_photo' => $aadharBackPhoto,
                'pan_number' => strtoupper($request->pan_number),
                'pan_photo' => $panPhoto,
                'bank_account_number' => $request->bank_account_number,
                'bank_ifsc_code' => strtoupper($request->bank_ifsc_code),
                'bank_name' => $request->bank_name,
                'account_holder_name' => $request->account_holder_name,
                'passbook_photo' => $passbookPhoto,
                'profile_photo' => $profilePhoto,
                'working_city' => $request->working_city,
                'kyc_status' => 'pending',
                'submitted_at' => Carbon::now(),
                'remark' => null, // Clear previous remarks
            ];

            if ($existingKyc) {
                // Delete old files if updating
                $this->deleteOldFiles($existingKyc);
                $existingKyc->update($kycData);
                $kyc = $existingKyc;
            } else {
                $kyc = KycVerification::create($kycData);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'KYC documents submitted successfully',
                'data' => $kyc->load('user:id,name,mobile,role')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            // Clean up uploaded files on error
            Storage::disk('public')->delete([
                $aadharPhoto ?? '',
                $aadharBackPhoto ?? '',
                $panPhoto ?? '',
                $passbookPhoto ?? '',
                $profilePhoto ?? ''
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit KYC documents',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's own KYC status
     */
    public function getMyKyc(Request $request)
    {
        if (!in_array($request->user()->role, ['agent', 'leader'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only agents and leaders can view KYC status'
            ], 403);
        }

        $kyc = KycVerification::where('user_id', $request->user()->id)
                              ->with(['user:id,name,mobile,role', 'approvedBy:id,name'])
                              ->first();

        if (!$kyc) {
            return response()->json([
                'success' => true,
                'message' => 'No KYC submitted yet',
                'data' => null
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $kyc
        ]);
    }

    /**
     * Admin: Get all KYC submissions with filters
     */
    public function getAllKyc(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Only admins can view all KYC submissions'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'nullable|in:pending,approved,rejected',
            'role' => 'nullable|in:agent,leader',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'search' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()->first()
            ], 422);
        }

        $query = KycVerification::with(['user:id,name,mobile,role', 'approvedBy:id,name']);

        // Apply filters
        if ($request->filled('status')) {
            $query->where('kyc_status', $request->status);
        }

        if ($request->filled('role')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('role', $request->role);
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('submitted_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('submitted_at', '<=', $request->date_to);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('aadhar_number', 'like', "%{$search}%")
                  ->orWhere('pan_number', 'like', "%{$search}%")
                  ->orWhere('bank_account_number', 'like', "%{$search}%")
                  ->orWhere('working_city', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%")
                        ->orWhere('mobile', 'like', "%{$search}%");
                  });
            });
        }

        $perPage = $request->get('per_page', 20);
        $kycs = $query->orderBy('submitted_at', 'desc')->paginate($perPage);

        // Get statistics
        $stats = [
            'total' => KycVerification::count(),
            'pending' => KycVerification::where('kyc_status', 'pending')->count(),
            'approved' => KycVerification::where('kyc_status', 'approved')->count(),
            'rejected' => KycVerification::where('kyc_status', 'rejected')->count(),
            'agents' => KycVerification::whereHas('user', function ($q) {
                $q->where('role', 'agent');
            })->count(),
            'leaders' => KycVerification::whereHas('user', function ($q) {
                $q->where('role', 'leader');
            })->count(),
            'today' => KycVerification::whereDate('submitted_at', Carbon::today())->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $kycs,
            'statistics' => $stats
        ]);
    }

    /**
     * Admin: Get specific KYC details
     */
    public function getKycDetails(Request $request, $id)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Only admins can view KYC details'
            ], 403);
        }

        $kyc = KycVerification::with(['user:id,name,mobile,role', 'approvedBy:id,name'])
                              ->find($id);

        if (!$kyc) {
            return response()->json([
                'success' => false,
                'message' => 'KYC record not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $kyc
        ]);
    }

    /**
     * Admin: Approve/Reject KYC
     */
    public function reviewKyc(Request $request, $id)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Only admins can review KYC'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:approved,rejected',
            'remark' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()->first()
            ], 422);
        }

        $kyc = KycVerification::find($id);

        if (!$kyc) {
            return response()->json([
                'success' => false,
                'message' => 'KYC record not found'
            ], 404);
        }

        if ($kyc->kyc_status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'KYC has already been reviewed'
            ], 400);
        }

        // Update KYC status
        if ($request->status === 'approved') {
            $kyc->approve($request->user()->id, $request->remark);
        } else {
            $kyc->reject($request->user()->id, $request->remark);
        }

        return response()->json([
            'success' => true,
            'message' => 'KYC ' . $request->status . ' successfully',
            'data' => $kyc->load(['user:id,name,mobile,role', 'approvedBy:id,name'])
        ]);
    }

    /**
     * Get pending KYCs for admin review
     */
    public function getPendingKycs(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Only admins can view pending KYCs'
            ], 403);
        }

        $kycs = KycVerification::where('kyc_status', 'pending')
                               ->with(['user:id,name,mobile,role'])
                               ->orderBy('submitted_at', 'asc')
                               ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $kycs
        ]);
    }

    /**
     * Helper method to store uploaded files
     */
    private function storeFile($file, $directory)
    {
        if (!$file) return null;
        
        return $file->store($directory, 'public');
    }

    /**
     * Helper method to delete old files when updating KYC
     */
    private function deleteOldFiles($kyc)
    {
        $files = [
            $kyc->aadhar_photo,
            $kyc->aadhar_back_photo,
            $kyc->pan_photo,
            $kyc->passbook_photo,
            $kyc->profile_photo
        ];

        foreach ($files as $file) {
            if ($file && Storage::disk('public')->exists($file)) {
                Storage::disk('public')->delete($file);
            }
        }
    }
}
