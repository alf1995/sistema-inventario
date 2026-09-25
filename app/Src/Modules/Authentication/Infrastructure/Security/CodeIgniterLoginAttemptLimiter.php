<?php

declare(strict_types=1);

namespace App\Modules\Authentication\Infrastructure\Security;

use App\Modules\Authentication\Application\Ports\LoginAttemptLimiterInterface;
use CodeIgniter\Throttle\Throttler;

final class CodeIgniterLoginAttemptLimiter implements LoginAttemptLimiterInterface
{
    private int $retryAfter = 0;

    public function __construct(
        private readonly Throttler $throttler,
        private readonly int $userCapacity,
        private readonly int $ipCapacity,
        private readonly int $windowSeconds,
    ) {
    }

    public function isBlocked(string $ipAddress, string $login): bool
    {
        $userAllowed = $this->throttler->check($this->userKey($login), $this->userCapacity, $this->windowSeconds, 0);
        $userRetry   = $this->throttler->getTokenTime();

        $ipAllowed = $this->throttler->check($this->ipKey($ipAddress), $this->ipCapacity, $this->windowSeconds, 0);
        $ipRetry   = $this->throttler->getTokenTime();

        $this->retryAfter = max($userRetry, $ipRetry);

        return ! $userAllowed || ! $ipAllowed;
    }

    public function registerFailure(string $ipAddress, string $login): void
    {
        $this->throttler->check($this->userKey($login), $this->userCapacity, $this->windowSeconds, 1);
        $this->throttler->check($this->ipKey($ipAddress), $this->ipCapacity, $this->windowSeconds, 1);
    }

    public function clear(string $ipAddress, string $login): void
    {
        $this->throttler->remove($this->userKey($login));
        $this->retryAfter = 0;
    }

    public function retryAfter(): int
    {
        return $this->retryAfter;
    }

    private function userKey(string $login): string
    {
        return 'auth_login_user_' . hash('sha256', mb_strtolower(trim($login)));
    }

    private function ipKey(string $ipAddress): string
    {
        return 'auth_login_ip_' . hash('sha256', trim($ipAddress));
    }
}
