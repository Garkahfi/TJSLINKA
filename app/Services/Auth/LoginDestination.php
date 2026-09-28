<?php

namespace App\Services\Auth;

use App\Models\User;

class LoginDestination
{
    /** @return array{guard:string,dashboard:string,profile:string}|null */
    public function for(User $user): ?array
    {
        $role = $user->role ?? ($user->is_admin ? 'admin' : null);

        return match ($role) {
            'admin' => ['guard' => 'admin', 'dashboard' => 'admin.home', 'profile' => 'admin.profile'],
            'super_admin' => ['guard' => 'superadmin', 'dashboard' => 'superadmin.home', 'profile' => 'superadmin.profile'],
            'pumk_admin' => ['guard' => 'pumk', 'dashboard' => 'pumk-admin.home', 'profile' => 'pumk-admin.profile'],
            default => null,
        };
    }

    public function routeFor(User $user): ?string
    {
        $destination = $this->for($user);

        return $destination === null ? null : $destination[$user->must_change_password ? 'profile' : 'dashboard'];
    }
}
