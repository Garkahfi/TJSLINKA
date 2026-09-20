<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PublicProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('pages.profile', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nama_depan' => ['required', 'string', 'max:100'],
            'nama_belakang' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'unique:users,email,'.$request->user()->id],
            'no_telephone' => ['nullable', 'string', 'max:30'],
            'jabatan' => ['nullable', 'string', 'max:100'],
            'alamat' => ['nullable', 'string'],
            'avatar' => ['nullable', 'image', 'max:2048'],
        ]);

        if ($request->hasFile('avatar')) {
            $data['avatar_path'] = $request->file('avatar')->store('avatars', 'public');
        }

        unset($data['avatar']);
        $data['name'] = trim($data['nama_depan'].' '.$data['nama_belakang']);
        $request->user()->update($data);

        return back()->with('success', 'Profil berhasil diperbarui.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password:web'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $request->user()->update([
            'password' => Hash::make($data['password']),
            'must_change_password' => false,
        ]);
        $request->session()->regenerate();

        return back()->with('success', 'Password berhasil diganti.');
    }
}
