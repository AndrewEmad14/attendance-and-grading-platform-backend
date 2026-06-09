<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Requests\RegisterRequest;
use Illuminate\Auth\Events\Registered;
class AuthController extends Controller
{
    
    public function login(Request $request)
    {
        $creds = $request->only('email', 'password');
        if(auth()->attempt($creds))
        {
            $token = auth()->user()->createToken('auth_token')->plainTextToken;
            return response()->json(['access_token' => $token], 200);
        }

        return response()->json(['message' => "Unauthoried"], 401);
    }
    public function me(Request $request)
    {
        return response()->json($request->user());
    }
}
