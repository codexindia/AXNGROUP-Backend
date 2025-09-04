<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AdminController extends Controller
{
    /**
     * Toggle user block status (Block/Unblock)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function toggleUserBlock(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id'
        ]);

        $userId = $request->user_id;
        
        // Prevent admin from blocking themselves
        if (auth()->id() == $userId) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot modify your own block status'
            ], 400);
        }

        $user = User::find($userId);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        // Prevent blocking other admins
        if ($user->role === 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot modify admin user block status'
            ], 400);
        }

        $newStatus = !$user->is_blocked;
        $user->update(['is_blocked' => $newStatus]);

        $action = $newStatus ? 'blocked' : 'unblocked';

        return response()->json([
            'success' => true,
            'message' => "User has been {$action} successfully",
            'data' => [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_blocked' => $user->is_blocked,
                'action' => $action
            ]
        ]);
    }
}
