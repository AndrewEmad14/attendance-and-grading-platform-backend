<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAccountExpiry
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->expires_at && $user->expires_at->isPast()) {
            $user->tokens()->delete(); // invalidate all tokens

            return response()->json(['message' => 'Account expired'], 403);
        }

        return $next($request);
    }
}
