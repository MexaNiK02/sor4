<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken() ?: $request->query('token');

        if (! $token) {
            return response()->json(['message' => 'Требуется вход ведущего.'], 401);
        }

        $user = User::where('api_token', $token)->first();

        if (! $user) {
            return response()->json(['message' => 'Сессия входа истекла. Войдите заново.'], 401);
        }

        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
