<?php

declare(strict_types=1);

namespace App\Modules\Authentication\Domain\Repositories;

use App\Modules\Authentication\Domain\Entities\User;
use DateTimeImmutable;

interface UserRepositoryInterface
{
    public function findByLogin(string $login): ?User;

    public function findById(int $id): ?User;

    public function touchLastActivity(int $userId, DateTimeImmutable $at): void;

    public function updatePasswordHash(int $userId, string $passwordHash): void;

    public function setPasswordHash(int $userId, string $passwordHash, bool $mustChangePassword): void;
}
