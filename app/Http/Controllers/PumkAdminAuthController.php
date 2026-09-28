<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PumkAdminAuthController extends Controller
{
    public function showChangePassword(): RedirectResponse
    {
        return redirect()->route('pumk-admin.profile');
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

        return redirect()->route('pumk-admin.profile')->with('success', 'Password berhasil diganti.');
    }
}
