<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = $request->user();
        abort_unless($user, 401); // osiguranje

        // dozvoli ako user-ova uloga postoji u listi
        abort_unless(in_array($user->role, $roles, true), 403, 'Zabranjen pristup za ovu ulogu.');
        return $next($request);
    }
}
