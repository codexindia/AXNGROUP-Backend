<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
//validate
use Illuminate\Support\Facades\Validator;
use App\Models\User;
class UserManagment extends Controller
{
    public function changePassword(Request $request)
    {
       $validate = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'password' => 'required|min:8',
        ]);

        if ($validate->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validate->errors()->first()
            ], 422);
        }

        $user = User::find($request->user_id);
        $user->password = bcrypt($request->password);
        $user->save();
        return response()->json([
            'status' => true,
            'message' => 'Password changed successfully'
        ], 200);
    }
}
