<?php

declare(strict_types=1);

namespace App\Modules\Authentication\Application\Services;

final class AccountPasswordResult
{
    private function __construct(
        public readonly bool $success,
        public readonly string $message,
        public readonly ?string $code = null,
    ) {
    }

    public static function success(string $message): self
    {
        return new self(true, $message);
    }

    public static function failure(string $message, string $code = 'validation'): self
    {
        return new self(false, $message, $code);
    }
}
