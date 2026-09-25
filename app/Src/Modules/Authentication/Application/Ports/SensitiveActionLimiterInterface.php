<?php

declare(strict_types=1);

namespace App\Modules\Authentication\Application\Ports;

interface SensitiveActionLimiterInterface
{
    public function isBlocked(string $action, int $userId, string $ipAddress): bool;

    public function registerFailure(string $action, int $userId, string $ipAddress): void;

    public function clear(string $action, int $userId, string $ipAddress): void;

    public function retryAfter(): int;
}
