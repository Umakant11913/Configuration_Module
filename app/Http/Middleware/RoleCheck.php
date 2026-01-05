<?php

namespace App\Http\Middleware;

use App\Providers\RouteServiceProvider;
use Closure;
use Illuminate\Http\Request;

class RoleCheck
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $hasRole = collect($roles)
            ->map(fn($item) => config('constants.roles.' . $item))
            ->containsStrict($request->user()->role);
        if (!$hasRole) {
            abort(403, 'Sorry! You are not authorized to access.');
        }
        return $next($request);
    }
}
