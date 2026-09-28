<?php

namespace App\Http\Middleware;

use App\Services\Auth\LoginDestination;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SynchronizeLoginSession
{
    private const PANEL_GUARDS = ['admin', 'superadmin', 'pumk'];

    public function __construct(private readonly LoginDestination $destination) {}

    public function handle(Request $request, Closure $next): Response
    {
        $web = Auth::guard('web');
        $user = $web->user();

        if ($user === null) {
            // Sessions from before the unified login may contain only a panel guard.
            foreach (self::PANEL_GUARDS as $guardName) {
                if (Auth::guard($guardName)->check()) {
                    $this->endSession($request);

                    return redirect()->route('login');
                }
            }

            return $next($request);
        }

        $user = $user->fresh();
        $target = $user !== null && $user->is_active ? $this->destination->for($user) : null;
        if ($target === null) {
            $this->endSession($request);

            return redirect()->route('login');
        }
        $web->setUser($user);

        foreach (self::PANEL_GUARDS as $guardName) {
            $panel = Auth::guard($guardName);
            if (! $panel->check()) {
                continue;
            }

            if ($guardName !== $target['guard'] || $panel->id() !== $user->id) {
                $this->endSession($request);

                return redirect()->route('login');
            }
        }

        $panel = Auth::guard($target['guard']);
        if (! $panel->check()) {
            $panel->login($user);
        } else {
            $panel->setUser($user);
        }

        $expectedGuard = $this->panelGuardForRoute($request);
        if ($expectedGuard !== null && $expectedGuard !== $target['guard']) {
            abort(403);
        }

        if ($user->must_change_password && $request->routeIs(
            'home', 'teras', 'program.*', 'public.profile', 'public.profile.update', 'public.password.update',
        )) {
            return redirect()->route($target['profile']);
        }

        return $next($request);
    }

    private function panelGuardForRoute(Request $request): ?string
    {
        $middleware = $request->route()?->gatherMiddleware() ?? [];

        foreach (self::PANEL_GUARDS as $guardName) {
            if (in_array('auth:'.$guardName, $middleware, true)) {
                return $guardName;
            }
        }

        return null;
    }

    private function endSession(Request $request): void
    {
        foreach (self::PANEL_GUARDS as $guardName) {
            Auth::guard($guardName)->logout();
        }
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
