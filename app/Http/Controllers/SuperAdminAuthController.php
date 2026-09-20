<?php

namespace App\Http\Controllers;

use App\Services\Auth\LoginAttemptLimiter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class SuperAdminAuthController extends Controller
{
    public function showLogin(): View
    {
        if (Auth::guard('superadmin')->check()) {
            Auth::guard('superadmin')->logout();
        }

        return view('superadmin.auth.login');
    }

    public function login(Request $request, LoginAttemptLimiter $limiter): RedirectResponse
    {
        $credentials = $request->validate(['username' => ['required', 'string', 'max:100'], 'password' => ['required', 'string']]);
        $credentials['is_active'] = true;
        if ($limiter->tooManyAttempts($request, 'superadmin', $credentials['username'])) {
            return back()->withErrors(['username' => 'Terlalu banyak percobaan login. Coba lagi dalam '.$limiter->availableIn($request, 'superadmin', $credentials['username']).' detik.'])->onlyInput('username');
        }
        $guard = Auth::guard('superadmin');
        if (! $guard->attempt($credentials, $request->boolean('remember'))) {
            $limiter->hit($request, 'superadmin', $credentials['username']);

            return back()->withErrors(['username' => 'Username atau password tidak sesuai.'])->onlyInput('username');
        }
        if ($guard->user()->role !== 'super_admin') {
            $guard->logout();
            $limiter->hit($request, 'superadmin', $credentials['username']);

            return back()->withErrors(['username' => 'Akun ini bukan akun Super Admin.'])->onlyInput('username');
        }
        $limiter->clear($request, 'superadmin', $credentials['username']);
        $request->session()->regenerate();
        $request->session()->forget('url.intended');

        return redirect()->route($guard->user()->must_change_password ? 'superadmin.profile' : 'superadmin.home');
    }

    public function showChangePassword(): RedirectResponse
    {
        return redirect()->route('superadmin.profile');
    }

    public function changePassword(Request $request): RedirectResponse
    {
        $data = $request->validate(['current_password' => ['required', 'current_password:superadmin'], 'password' => ['required', 'confirmed', 'different:current_password', Password::min(8)->letters()->numbers()]]);
        $request->user()->update(['password' => Hash::make($data['password']), 'must_change_password' => false]);
        $request->session()->regenerate();

        return redirect()->route('superadmin.profile')->with('success', 'Password berhasil diganti.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('superadmin')->logout();
        $request->session()->regenerateToken();

        return redirect()->route('superadmin.login');
    }
}
