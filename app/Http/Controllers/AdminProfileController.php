<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminProfileController extends Controller
{
    public function edit(Request $r): View
    {
        return view('admin.profile', ['user' => $r->user()]);
    }

    public function update(Request $r): RedirectResponse
    {
        $data = $r->validate(['nama_depan' => ['required', 'string', 'max:100'], 'nama_belakang' => ['required', 'string', 'max:100'], 'email' => ['required', 'email', 'unique:users,email,'.$r->user()->id], 'no_telephone' => ['nullable', 'string', 'max:30'], 'jabatan' => ['nullable', 'string', 'max:100'], 'alamat' => ['nullable', 'string'], 'avatar' => ['nullable', 'image', 'max:2048']]);
        if ($r->hasFile('avatar')) {
            $data['avatar_path'] = $r->file('avatar')->store('avatars', 'public');
        }unset($data['avatar']);
        $data['name'] = trim($data['nama_depan'].' '.$data['nama_belakang']);
        $r->user()->update($data);

        return back()->with('success', 'Profil berhasil diperbarui.');
    }
}
