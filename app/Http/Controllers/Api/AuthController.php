<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
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
            return response()->json([
                'message' => 'Invalid credentials',
                'status'  => 401,
            ], 401);
        }

        $user = auth()->user();

        // blocks the login if the account is expired
        if ($user->expires_at && now()->isAfter($user->expires_at)) {
            auth()->logout();

            return response()->json([
                'message' => 'Account expired',
                'status'  => 403,
            ], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'logged in successfully',
            'status'  => 200,
            'data'    => [
                'access_token' => $token,
                'role'         => $user->role,
                'expires_at'   => $user->expires_at,
                'user'         => [
                    'id'   => $user->id,
                    'name' => $user->name,
                ],
            ],
        ], 200);
    }

    public function me(Request $request)
    {
        $user = $request->user()->load(match (request()->user()->role) {
            Role::STUDENT => [
                'studentProfile.cohort.track',
                'studentProfile.labGroup',
                'studentProfile.tags',
            ],
            default => [
                'staffProfile.managedCohorts.cohort.track',
            ],
        });

        return response()->json([
            'message' => 'fetched user successfully',
            'status'  => 200,
            'data'    => $user,
        ], 200);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'logged out successfully',
            'status'  => 200,
        ], 200);
    }

    public function logoutAll(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'message' => 'logged out from all devices successfully',
            'status'  => 200,
        ], 200);
    }

    public function forgotPassword(ForgetPasswordRequest $request)
    {
        $status = Password::sendResetLink(['email' => $request->email]);

        return $status === Password::RESET_LINK_SENT
            ? response()->json([
                'message' => 'if the email exists, a verification link will be sent',
                'status'  => 200,
            ], 200)
            : response()->json([
                'message' => 'unable to send reset link',
                'status'  => 400,
            ], 400);
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
            ? response()->json([
                'message' => 'password reset successfully',
                'status'  => 200,
            ], 200)
            : response()->json([
                'message' => 'invalid or expired reset token',
                'status'  => 400,
            ], 400);
    }
}