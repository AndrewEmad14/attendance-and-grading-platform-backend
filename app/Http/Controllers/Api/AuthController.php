<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $creds = $request->only('email', 'password');

        if (! auth()->attempt($creds)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $user = auth()->user();

        // blocks the login if the account is expired
        if ($user->expires_at && now()->isAfter($user->expires_at)) {
            auth()->logout();

            return response()->json(['message' => 'Account expired'], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'role' => $user->role,
            'expires_at' => $user->expires_at,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
            ],
        ], 200);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function logoutAll(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Logged out from all devices successfully']);
    }
}
