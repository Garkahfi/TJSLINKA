<?php

namespace App\Http\Controllers;

use App\Services\Auth\LoginAttemptLimiter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PublicAuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::guard('web')->check()) {
            return redirect()->route('home');
        }

        return view('pages.login');
    }

    public function login(Request $request, LoginAttemptLimiter $limiter): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string'],
        ]);
        $credentials['is_active'] = true;

        if ($limiter->tooManyAttempts($request, 'web', $credentials['username'])) {
            return back()->withErrors(['username' => 'Terlalu banyak percobaan login. Coba lagi dalam '.$limiter->availableIn($request, 'web', $credentials['username']).' detik.'])->onlyInput('username');
        }

        $guard = Auth::guard('web');

        if (! $guard->attempt($credentials, $request->boolean('remember'))) {
            $limiter->hit($request, 'web', $credentials['username']);

            return back()
                ->withErrors(['username' => 'Username atau password tidak sesuai.'])
                ->onlyInput('username');
        }

        $limiter->clear($request, 'web', $credentials['username']);

        $request->session()->regenerate();
        $request->session()->forget('url.intended');

        return redirect()->route('home');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
