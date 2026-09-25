<?php

declare(strict_types=1);

namespace App\Modules\Authentication\Application\Services;

use App\Modules\Authentication\Domain\Entities\User;

final class SessionValidationResult
{
    private function __construct(
        public readonly bool $valid,
        public readonly string $code,
        public readonly ?User $user = null,
    ) {
    }

    public static function valid(User $user): self
    {
        return new self(true, 'valid', $user);
    }

    public static function invalid(string $code): self
    {
        return new self(false, $code);
    }
}
