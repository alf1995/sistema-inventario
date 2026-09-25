<?php

declare(strict_types=1);

namespace App\Shared\Application\Ports;

interface SecurityAuditLoggerInterface
{
    public function log(
        ?int $actorUserId,
        ?int $targetUserId,
        string $action,
        string $ipAddress,
        string $userAgent,
        array $metadata = [],
    ): void;
}
