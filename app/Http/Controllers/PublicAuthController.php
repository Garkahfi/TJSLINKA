<?php

namespace App\Http\Controllers;

use App\Services\Auth\LoginAttemptLimiter;
use App\Services\Auth\LoginDestination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PublicAuthController extends Controller
{
    public function entry(LoginDestination $destination): RedirectResponse
    {
        $user = Auth::guard('web')->user();

        return redirect()->route($user ? $destination->routeFor($user) ?? 'login' : 'login');
    }

    public function showLogin(LoginDestination $destination): View|RedirectResponse
    {
        $user = Auth::guard('web')->user();
        if ($user !== null) {
            return redirect()->route($destination->routeFor($user) ?? 'login');
        }

        return view('pages.login');
    }

    public function login(Request $request, LoginAttemptLimiter $limiter, LoginDestination $destination): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string'],
        ]);
        $credentials['is_active'] = true;

        if ($limiter->tooManyAttempts($request, 'unified', $credentials['username'])) {
            return back()->withErrors(['username' => 'Terlalu banyak percobaan login. Coba lagi dalam '.$limiter->availableIn($request, 'unified', $credentials['username']).' detik.'])->onlyInput('username');
        }

        $this->clearGuards($request);
        $guard = Auth::guard('web');
        if (! $guard->attempt($credentials, $request->boolean('remember'))
            || $destination->for($guard->user()) === null) {
            $this->clearGuards($request);
            $limiter->hit($request, 'unified', $credentials['username']);

            return back()
                ->withErrors(['username' => 'Username atau password tidak sesuai.'])
                ->onlyInput('username');
        }

        $limiter->clear($request, 'unified', $credentials['username']);

        $request->session()->regenerate();
        $request->session()->forget('url.intended');
        $panel = $destination->for($guard->user());
        Auth::guard($panel['guard'])->login($guard->user());

        return redirect()->route($destination->routeFor($guard->user()));
    }

    public function logout(Request $request): RedirectResponse
    {
        $this->clearGuards($request);

        return redirect()->route('login');
    }

    private function clearGuards(Request $request): void
    {
        foreach (['admin', 'superadmin', 'pumk', 'web'] as $guard) {
            Auth::guard($guard)->logout();
        }
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
