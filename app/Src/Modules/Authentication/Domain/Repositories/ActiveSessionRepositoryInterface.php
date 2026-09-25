<?php

declare(strict_types=1);

namespace App\Modules\Authentication\Domain\Repositories;

use App\Modules\Authentication\Domain\Entities\ActiveSession;
use DateTimeImmutable;

interface ActiveSessionRepositoryInterface
{
    public function deactivateExpiredForUser(int $userId, DateTimeImmutable $now, DateTimeImmutable $inactiveBefore): void;

    public function deactivateExpired(DateTimeImmutable $now, DateTimeImmutable $inactiveBefore): void;

    public function countActiveForUser(int $userId): int;

    public function create(
        int $userId,
        string $tokenHash,
        string $ipAddress,
        string $userAgent,
        DateTimeImmutable $lastActivity,
        DateTimeImmutable $expiresAt,
    ): void;

    public function findActive(int $userId, string $tokenHash): ?ActiveSession;

    public function touch(int $sessionId, DateTimeImmutable $lastActivity, DateTimeImmutable $expiresAt): void;

    public function deactivate(int $userId, string $tokenHash): void;

    public function deactivateAllForUser(int $userId): void;

    public function deactivateAllForUserExcept(int $userId, string $tokenHashToKeep): void;
}
