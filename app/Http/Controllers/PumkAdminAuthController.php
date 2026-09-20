<?php

namespace App\Http\Controllers;

use App\Services\Auth\LoginAttemptLimiter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PumkAdminAuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::guard('pumk')->check()) {
            return redirect()->route(Auth::guard('pumk')->user()->must_change_password ? 'pumk-admin.password.edit' : 'pumk-admin.home');
        }

        return view('pumk-admin.auth.login');
    }

    public function login(Request $request, LoginAttemptLimiter $limiter): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string'],
        ]);
        $credentials['is_active'] = true;
        $credentials['role'] = 'pumk_admin';

        if ($limiter->tooManyAttempts($request, 'pumk', $credentials['username'])) {
            return back()->withErrors(['username' => 'Terlalu banyak percobaan login. Coba lagi dalam '.$limiter->availableIn($request, 'pumk', $credentials['username']).' detik.'])->onlyInput('username');
        }

        $guard = Auth::guard('pumk');

        if (! $guard->attempt($credentials, $request->boolean('remember'))) {
            $limiter->hit($request, 'pumk', $credentials['username']);

            return back()
                ->withErrors(['username' => 'Username atau password tidak sesuai.'])
                ->onlyInput('username');
        }

        $limiter->clear($request, 'pumk', $credentials['username']);

        $request->session()->regenerate();
        $request->session()->forget('url.intended');

        return redirect()->route($guard->user()->must_change_password ? 'pumk-admin.password.edit' : 'pumk-admin.home');
    }

    public function showChangePassword(): View
    {
        return view('pumk-admin.auth.change-password');
    }

    public function changePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password:pumk'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(8)->letters()->numbers()],
        ]);

        $request->user()->update([
            'password' => Hash::make($data['password']),
            'must_change_password' => false,
        ]);
        $request->session()->regenerate();

        return redirect()->route('pumk-admin.home')->with('success', 'Password berhasil diganti.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('pumk')->logout();
        $request->session()->regenerateToken();

        return redirect()->route('pumk-admin.login');
    }
}
