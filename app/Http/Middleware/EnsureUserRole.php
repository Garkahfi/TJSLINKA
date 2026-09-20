<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();
        $effectiveRole = $user?->role ?? ($user?->is_admin ? 'admin' : null);
        abort_unless($user && $user->is_active !== false, 403);
        abort_unless($effectiveRole === $role, 403);

        return $next($request);
    }
}
