<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForgetPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $creds = $request->only('email', 'password');

        if (! auth()->attempt($creds)) {
            return response()->json(['message' => 'Invalid credentials'], 401)->setStatusCode(401);
        }

        $user = auth()->user();

        // blocks the login if the account is expired
        if ($user->expires_at && now()->isAfter($user->expires_at)) {
            auth()->logout();

            return response()->json(['message' => 'Account expired'], 403)->setStatusCode(403);
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
        ], 200)->setStatusCode(200);
    }

    public function me(Request $request)
    {
        return response()->json($request->user())->setStatusCode(200);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully'])->setStatusCode(200);
    }

    public function logoutAll(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Logged out from all devices successfully'])->setStatusCode(200);
    }

    public function forgotPassword(ForgetPasswordRequest $request)
    {

        $status = Password::sendResetLink(['email' => $request->email]);

        return $status === Password::RESET_LINK_SENT
            ? response()->json(['message' => 'if the email exsist, a verfication link will be sent'], 200)
            : response()->json(['message' => 'Unable to send reset link'], 400);
    }

    public function resetPassword(ResetPasswordRequest $request)
    {

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->update(['password_hash' => $password]);
                $user->tokens()->delete(); // invalidate all old tokens
            }
        );

        return $status === Password::PASSWORD_RESET
            ? response()->json(['message' => 'Password reset successfully'], 200)
            : response()->json(['message' => 'Invalid or expired reset token'], 400);
    }
}
