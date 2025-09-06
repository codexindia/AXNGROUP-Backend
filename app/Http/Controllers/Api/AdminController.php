<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

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

    /**
     * Get users list with filters (Admin only)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getUsersList(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:agents,leaders,all',
            'search' => 'nullable|string|max:255',
            'is_blocked' => 'nullable|boolean',
            'leader_id' => 'nullable|exists:users,id',
            'per_page' => 'nullable|integer|min:1|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $query = User::query();

        // Filter by type
        switch ($request->type) {
            case 'agents':
                $query->where('role', 'agent');
                break;
            case 'leaders':
                $query->where('role', 'leader');
                break;
            case 'all':
                // No role filter for all users
                break;
        }

        // Filter agents by leader if leader_id provided
        if ($request->filled('leader_id') && $request->type === 'agents') {
            $query->where('parent_id', $request->leader_id);
        }

        // Apply search filter
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                  ->orWhere('email', 'like', "%{$searchTerm}%")
                  ->orWhere('mobile', 'like', "%{$searchTerm}%")
                  ->orWhere('unique_id', 'like', "%{$searchTerm}%");
            });
        }

        // Apply blocked status filter
        if ($request->has('is_blocked')) {
            $query->where('is_blocked', $request->boolean('is_blocked'));
        }

        // Select fields and relationships
        $query->select(['id', 'unique_id', 'name', 'email', 'mobile', 'role', 'parent_id', 'is_blocked', 'created_at'])
              ->with(['parent:id,name,unique_id']);

        // Add agent count for leaders
        if ($request->type === 'leaders') {
            $query->withCount('agents');
        }

        $users = $query->orderBy('created_at', 'desc')
                      ->paginate($request->get('per_page', 20));

        // Get statistics based on type
        $stats = [];
        switch ($request->type) {
            case 'agents':
                $stats = [
                    'total_agents' => User::where('role', 'agent')->count(),
                    'blocked_agents' => User::where('role', 'agent')->where('is_blocked', true)->count(),
                    'active_agents' => User::where('role', 'agent')->where('is_blocked', false)->count(),
                ];
                break;
            case 'leaders':
                $stats = [
                    'total_leaders' => User::where('role', 'leader')->count(),
                    'blocked_leaders' => User::where('role', 'leader')->where('is_blocked', true)->count(),
                    'active_leaders' => User::where('role', 'leader')->where('is_blocked', false)->count(),
                ];
                break;
            case 'all':
                $stats = [
                    'total_users' => User::count(),
                    'total_agents' => User::where('role', 'agent')->count(),
                    'total_leaders' => User::where('role', 'leader')->count(),
                    'total_admins' => User::where('role', 'admin')->count(),
                    'blocked_users' => User::where('is_blocked', true)->count(),
                ];
                break;
        }

        return response()->json([
            'success' => true,
            'data' => $users,
            'statistics' => $stats
        ]);
    }
}
