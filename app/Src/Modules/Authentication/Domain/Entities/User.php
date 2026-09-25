<?php

declare(strict_types=1);

namespace App\Modules\Authentication\Domain\Entities;

final class User
{
    
    public function __construct(
        public readonly int $id,
        public readonly string $username,
        public readonly string $email,
        public readonly string $passwordHash,
        public readonly string $status,
        public readonly bool $mustChangePassword,
        public readonly array $roles = [],
    ) {
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function primaryRole(): string
    {
        return $this->roles[0] ?? 'Sin rol';
    }
}
