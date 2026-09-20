<?php

namespace App\Services\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class LoginAttemptLimiter
{
    private const MAX_ACCOUNT_ATTEMPTS = 5;

    private const MAX_IP_ATTEMPTS = 30;

    private const DECAY_SECONDS = 60;

    public function tooManyAttempts(Request $request, string $scope, string $username): bool
    {
        return RateLimiter::tooManyAttempts($this->accountKey($request, $scope, $username), self::MAX_ACCOUNT_ATTEMPTS)
            || RateLimiter::tooManyAttempts($this->ipKey($request, $scope), self::MAX_IP_ATTEMPTS);
    }

    public function availableIn(Request $request, string $scope, string $username): int
    {
        return max(
            RateLimiter::availableIn($this->accountKey($request, $scope, $username)),
            RateLimiter::availableIn($this->ipKey($request, $scope)),
        );
    }

    public function hit(Request $request, string $scope, string $username): void
    {
        RateLimiter::hit($this->accountKey($request, $scope, $username), self::DECAY_SECONDS);
        RateLimiter::hit($this->ipKey($request, $scope), self::DECAY_SECONDS);
    }

    public function clear(Request $request, string $scope, string $username): void
    {
        RateLimiter::clear($this->accountKey($request, $scope, $username));
    }

    private function accountKey(Request $request, string $scope, string $username): string
    {
        return 'login:account:'.hash('sha256', $scope.'|'.mb_strtolower(trim($username)).'|'.$request->ip());
    }

    private function ipKey(Request $request, string $scope): string
    {
        return 'login:ip:'.hash('sha256', $scope.'|'.$request->ip());
    }
}
