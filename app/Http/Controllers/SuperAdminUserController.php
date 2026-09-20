<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SuperAdminUserController extends Controller
{
    private const MANAGED_ROLES = ['admin', 'pumk_admin'];

    private const ROLE_LABELS = [
        'admin' => 'Admin TJSL',
        'pumk_admin' => 'Admin PUMK',
    ];

    public function index(): View
    {
        return view('superadmin.users.index', [
            'users' => User::query()
                ->whereIn('role', self::MANAGED_ROLES)
                ->orderBy('role')
                ->orderBy('name')
                ->get(),
            'roleLabels' => self::ROLE_LABELS,
        ]);
    }

    public function create(): View
    {
        return view('superadmin.users.form', [
            'account' => new User,
            'roleLabels' => self::ROLE_LABELS,
        ]);
    }

    public function store(Request $r): RedirectResponse
    {
        $data = $this->data($r);
        $data['name'] = trim($data['nama_depan'].' '.$data['nama_belakang']);
        $data['password'] = Hash::make($data['password']);
        $data['is_admin'] = true;
        $data['is_active'] = true;
        $data['must_change_password'] = true;
        User::create($data);

        return redirect()->route('superadmin.users.index')->with('success', 'Akun Admin berhasil dibuat.');
    }

    public function edit(User $user): View
    {
        $this->ensureManagedAccount($user);

        return view('superadmin.users.form', [
            'account' => $user,
            'roleLabels' => self::ROLE_LABELS,
        ]);
    }

    public function update(Request $r, User $user): RedirectResponse
    {
        $this->ensureManagedAccount($user);
        $data = $this->data($r, $user);
        unset($data['password']);
        $data['name'] = trim($data['nama_depan'].' '.$data['nama_belakang']);
        $user->update($data);

        return redirect()->route('superadmin.users.index')->with('success', 'Akun Admin diperbarui.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->ensureManagedAccount($user);
        $user->update(['is_active' => false]);

        return back()->with('success', 'Akun Admin dinonaktifkan.');
    }

    private function data(Request $r, ?User $user = null): array
    {
        return $r->validate([
            'role' => ['required', Rule::in(self::MANAGED_ROLES)],
            'nama_depan' => ['required', 'string', 'max:100'],
            'nama_belakang' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:100', Rule::unique('users')->ignore($user?->id)],
            'email' => ['required', 'email', Rule::unique('users')->ignore($user?->id)],
            'jabatan' => ['nullable', 'string', 'max:100'],
            'no_telephone' => ['nullable', 'string', 'max:30'],
            'alamat' => ['nullable', 'string'],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8'],
        ]);
    }

    private function ensureManagedAccount(User $user): void
    {
        abort_unless(in_array($user->role, self::MANAGED_ROLES, true), 404);
    }
}
