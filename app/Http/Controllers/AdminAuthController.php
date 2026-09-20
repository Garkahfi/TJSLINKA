<?php

namespace App\Http\Controllers;

use App\Services\Auth\LoginAttemptLimiter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AdminAuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::guard('admin')->check()) {
            return redirect()->route(Auth::guard('admin')->user()->must_change_password ? 'admin.profile' : 'admin.home');
        }

        return view('admin.auth.login');
    }

    public function login(Request $request, LoginAttemptLimiter $limiter): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string'],
        ]);
        $credentials['is_active'] = true;
        $credentials['role'] = 'admin';

        if ($limiter->tooManyAttempts($request, 'admin', $credentials['username'])) {
            return back()->withErrors(['username' => 'Terlalu banyak percobaan login. Coba lagi dalam '.$limiter->availableIn($request, 'admin', $credentials['username']).' detik.'])->onlyInput('username');
        }

        $guard = Auth::guard('admin');

        if (! $guard->attempt($credentials, $request->boolean('remember'))) {
            $limiter->hit($request, 'admin', $credentials['username']);

            return back()->withErrors(['username' => 'Username atau password tidak sesuai.'])->onlyInput('username');
        }

        $limiter->clear($request, 'admin', $credentials['username']);

        $request->session()->regenerate();
        $request->session()->forget('url.intended');

        return redirect()->route($guard->user()->must_change_password ? 'admin.profile' : 'admin.home');
    }

    public function showChangePassword(): RedirectResponse
    {
        return redirect()->route('admin.profile');
    }

    public function changePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password:admin'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(8)->letters()->numbers()],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
        ]);

        $request->session()->regenerate();

        return redirect()->route('admin.profile')->with('success', 'Password berhasil diganti.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
