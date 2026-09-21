<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminPasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $role = $user?->role ?? ($user?->is_admin ? 'admin' : null);
        abort_unless($user && in_array($role, ['admin', 'super_admin', 'pumk_admin'], true), 403);

        if ($user->must_change_password) {
            $prefix = match ($role) {
                'super_admin' => 'superadmin',
                'pumk_admin' => 'pumk-admin',
                default => 'admin',
            };

            if (! $request->routeIs(
                $prefix.'.logout',
                $prefix.'.password.edit',
                $prefix.'.password.update',
                $prefix.'.profile',
                $prefix.'.profile.update',
            )) {
                return redirect()->route($prefix.'.profile')
                    ->with('password_change_required', 'Ganti password sementara sebelum melanjutkan.');
            }
        }

        return $next($request);
    }
}
