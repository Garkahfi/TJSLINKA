<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $this->createIfMissing('TJSLINKA', 'admin_initial_password', [
            'name' => 'Admin TJSL INKA',
            'nama_depan' => 'Admin',
            'nama_belakang' => 'TJSL INKA',
            'email' => 'admin@tjslinka.local',
            'jabatan' => 'Karyawan',
            'role' => 'admin',
        ]);
        $this->createIfMissing('TJSLINKAMIN', 'superadmin_initial_password', [
            'name' => 'Super Admin',
            'nama_depan' => 'Super',
            'nama_belakang' => 'Admin',
            'email' => 'superadmin@lensatjslinka.local',
            'jabatan' => 'Super Admin',
            'role' => 'super_admin',
        ]);
    }

    private function createIfMissing(string $username, string $configKey, array $attributes): void
    {
        if (User::where('username', $username)->exists()) {
            return;
        }

        $password = (string) config('bootstrap-users.'.$configKey);
        if (strlen($password) < 12) {
            throw new RuntimeException('Password awal untuk '.$username.' wajib diatur melalui konfigurasi dan minimal 12 karakter.');
        }

        User::create(array_merge($attributes, [
            'username' => $username,
            'password' => Hash::make($password),
            'no_telephone' => null,
            'alamat' => null,
            'is_admin' => true,
            'must_change_password' => true,
            'is_active' => true,
        ]));
    }
}
