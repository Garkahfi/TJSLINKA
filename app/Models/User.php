<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'username', 'nama_depan', 'nama_belakang', 'email', 'password', 'no_telephone', 'jabatan', 'alamat', 'avatar_path', 'role', 'is_admin', 'must_change_password', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function programs()
    {
        return $this->hasMany(Program::class, 'created_by');
    }

    public function bantuanCsr()
    {
        return $this->hasMany(BantuanCsr::class, 'created_by');
    }

    public function adminNotifications()
    {
        return $this->hasMany(AdminNotification::class);
    }

    public function pumkImportBatches()
    {
        return $this->hasMany(PumkImportBatch::class, 'imported_by');
    }

    public function pumkMitraCreated()
    {
        return $this->hasMany(PumkMitra::class, 'created_by');
    }

    public function pumkPinjamanCreated()
    {
        return $this->hasMany(PumkPinjaman::class, 'created_by');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'must_change_password' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
