<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class PumkAdminSeeder extends Seeder
{
    public function run(): void
    {
        $username = (string) config('pumk.admin.username');
        $password = (string) config('pumk.admin.password');
        $user = User::where('username', $username)->first();

        if ($user && ! in_array($user->role, ['admin', 'pumk_admin'], true)) {
            throw new RuntimeException("Username {$username} tidak dapat dialihkan dari role {$user->role}.");
        }

        if ((! $user || $user->role !== 'pumk_admin') && strlen($password) < 12) {
            throw new RuntimeException('Password awal Admin PUMK wajib diatur dan minimal 12 karakter.');
        }

        if (! $user) {
            $user = User::create([
                'name' => 'Admin PUMK INKA',
                'username' => $username,
                'nama_depan' => 'Admin',
                'nama_belakang' => 'PUMK INKA',
                'email' => (string) config('pumk.admin.email'),
                'password' => Hash::make($password),
                'no_telephone' => null,
                'jabatan' => 'Admin PUMK',
                'alamat' => null,
                'role' => 'pumk_admin',
                'is_admin' => true,
                'must_change_password' => true,
                'is_active' => true,
            ]);
        } else {
            $isFirstPromotion = $user->role !== 'pumk_admin';

            $user->forceFill([
                'role' => 'pumk_admin',
                'is_admin' => true,
                'must_change_password' => $isFirstPromotion
                    || $user->must_change_password
                    || ($password !== '' && Hash::check($password, $user->password)),
                'is_active' => true,
            ]);

            // Password hanya disetel saat akun lama pertama kali dialihkan.
            // Menjalankan seeder lagi tidak boleh mereset password yang sudah diganti user.
            if ($isFirstPromotion) {
                $user->password = Hash::make($password);
            }

            $user->save();
        }

        $legacyUsername = (string) config('pumk.admin.legacy_username');
        $legacyEmail = (string) config('pumk.admin.legacy_email');

        if ($legacyUsername !== '' && $legacyUsername !== $username) {
            User::query()
                ->where('username', $legacyUsername)
                ->where('email', $legacyEmail)
                ->where('role', 'pumk_admin')
                ->update(['is_active' => false]);
        }
    }
}
