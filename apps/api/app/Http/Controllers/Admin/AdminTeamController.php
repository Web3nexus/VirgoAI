<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Admin management: the people who can open the owner console. Separate from user management, which covers customers.
 */
class AdminTeamController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => User::query()->where('role', User::ROLE_SUPER_ADMIN)->orderBy('id')->get()->map(fn (User $admin) => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'suspended' => $admin->isSuspended(),
            ])->values(),
        ]);
    }

    /** Grant console access to an existing account. New accounts are created on the Users page first. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'string', 'email']]);

        $user = User::query()->where('email', strtolower($data['email']))->first();

        if ($user === null) {
            throw ValidationException::withMessages(['email' => 'No account has this email. Create the user on the User management page first.']);
        }

        if ($user->isSuperAdmin()) {
            throw ValidationException::withMessages(['email' => 'This account is already an admin.']);
        }

        $user->forceFill(['role' => User::ROLE_SUPER_ADMIN])->save();
        AdminAuditLog::record($request->user(), 'team.granted', $user);

        return response()->json(['message' => "{$user->email} can now open the owner console."], 201);
    }

    /** Remove console access. The owner cannot remove themselves, and at least one admin must remain. */
    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->is($request->user())) {
            throw ValidationException::withMessages(['user' => 'You cannot remove your own admin access.']);
        }

        if (! $user->isSuperAdmin()) {
            throw ValidationException::withMessages(['user' => 'This account is not an admin.']);
        }

        $user->forceFill(['role' => User::ROLE_USER])->save();
        AdminAuditLog::record($request->user(), 'team.revoked', $user);

        return response()->json(['message' => 'Admin access removed.']);
    }
}
