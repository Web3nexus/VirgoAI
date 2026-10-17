<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminAuthController extends Controller
{
    /**
     * Sign in at /securegate. Every failure, including a valid login that is not a super admin, gets the
     * same answer, so the page does not reveal which accounts are admins.
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        $allowed = $user !== null
            && Hash::check($data['password'], $user->password)
            && $user->isSuperAdmin()
            && ! $user->isSuspended();

        if (! $allowed) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        AdminAuditLog::record($user, 'admin.login');

        return response()->json([
            'user' => new UserResource($user),
            'token' => $user->createToken('admin')->plainTextToken,
        ]);
    }
}
