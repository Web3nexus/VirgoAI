<?php

namespace App\Http\Controllers\Api;

use App\Billing\Money;
use App\Billing\Wallet;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Jobs\SendTemplatedEmail;
use App\Models\User;
use App\Settings\SystemSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a new user and issue an API token.
     */
    public function register(Request $request): JsonResponse
    {
        if (! SystemSettings::registrationEnabled()) {
            return response()->json(['message' => 'New sign-ups are closed right now.'], 403);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        if (SystemSettings::welcomeCreditUsd() > 0) {
            app(Wallet::class)->adjust($user, Money::fromUsd(SystemSettings::welcomeCreditUsd()), 'Welcome credit', null);
        }

        SendTemplatedEmail::dispatch('welcome', $user->email, ['name' => $user->name], $user->id);

        $token = $user->createToken('web')->plainTextToken;

        return response()->json([
            'user' => new UserResource($user->refresh()),
            'token' => $token,
        ], 201);
    }

    /**
     * Authenticate a user and issue an API token.
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        if ($user->isSuspended()) {
            return response()->json(['message' => 'This account is suspended. Contact the owner.'], 403);
        }

        $token = $user->createToken('web')->plainTextToken;

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token,
        ]);
    }

    /**
     * Return the currently authenticated user.
     */
    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    /**
     * Revoke the token used for the current request.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }
}
