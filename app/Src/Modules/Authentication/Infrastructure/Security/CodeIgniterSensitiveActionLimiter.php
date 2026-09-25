<?php

declare(strict_types=1);

namespace App\Modules\Authentication\Infrastructure\Security;

use App\Modules\Authentication\Application\Ports\SensitiveActionLimiterInterface;
use CodeIgniter\Throttle\Throttler;

final class CodeIgniterSensitiveActionLimiter implements SensitiveActionLimiterInterface
{
    private int $lastRetryAfter = 0;

    public function __construct(
        private readonly Throttler $throttler,
        private readonly int $attempts,
        private readonly int $windowSeconds,
    ) {
    }

    public function isBlocked(string $action, int $userId, string $ipAddress): bool
    {
        $key = $this->key($action, $userId, $ipAddress);
        $allowed = $this->throttler->check($key, $this->attempts, $this->windowSeconds, 0);

        if (! $allowed) {
            $this->lastRetryAfter = max(1, $this->throttler->getTokenTime());
        }

        return ! $allowed;
    }

    public function registerFailure(string $action, int $userId, string $ipAddress): void
    {
        $key = $this->key($action, $userId, $ipAddress);
        $this->throttler->check($key, $this->attempts, $this->windowSeconds);
        $this->lastRetryAfter = max(1, $this->throttler->getTokenTime());
    }

    public function clear(string $action, int $userId, string $ipAddress): void
    {
        $this->throttler->remove($this->key($action, $userId, $ipAddress));
        $this->lastRetryAfter = 0;
    }

    public function retryAfter(): int
    {
        return $this->lastRetryAfter;
    }

    private function key(string $action, int $userId, string $ipAddress): string
    {
        return 'sensitive_' . hash('sha256', $action . '|' . $userId . '|' . $ipAddress);
    }
}
