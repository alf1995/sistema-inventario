<?php

declare(strict_types=1);

namespace App\Modules\Authentication\Application\Services;

use App\Modules\Authentication\Domain\Entities\User;

final class AuthenticationResult
{
    private function __construct(
        public readonly bool $success,
        public readonly string $code,
        public readonly string $message,
        public readonly ?User $user = null,
        public readonly ?string $sessionToken = null,
        public readonly int $retryAfter = 0,
    ) {
    }

    public static function success(User $user, string $sessionToken): self
    {
        return new self(true, 'authenticated', 'Autenticación correcta.', $user, $sessionToken);
    }

    public static function failure(string $code, string $message, int $retryAfter = 0): self
    {
        return new self(false, $code, $message, null, null, $retryAfter);
    }
}
