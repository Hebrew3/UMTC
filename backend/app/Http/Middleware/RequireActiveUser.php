<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RequireActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()?->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json(['message' => 'This account has been deactivated.'], 403);
        }

        return $next($request);
    }
}
