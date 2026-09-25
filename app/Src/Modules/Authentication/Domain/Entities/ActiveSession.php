<?php

declare(strict_types=1);

namespace App\Modules\Authentication\Domain\Entities;

use DateTimeImmutable;

final class ActiveSession
{
    public function __construct(
        public readonly int $id,
        public readonly int $userId,
        public readonly string $tokenHash,
        public readonly DateTimeImmutable $lastActivity,
        public readonly DateTimeImmutable $expiresAt,
        public readonly bool $isActive,
    ) {
    }
}
