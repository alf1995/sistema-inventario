<?php

declare(strict_types=1);

namespace App\Modules\Authentication\Application\Ports;

interface LoginAttemptLimiterInterface
{
    public function isBlocked(string $ipAddress, string $login): bool;

    public function registerFailure(string $ipAddress, string $login): void;

    public function clear(string $ipAddress, string $login): void;

    public function retryAfter(): int;
}
