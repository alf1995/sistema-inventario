<?php

declare(strict_types=1);

namespace App\Modules\Administration\Application\Services;

final class AdministrationResult
{
    private function __construct(
        public readonly bool $success,
        public readonly string $message,
        public readonly ?int $id = null,
        public readonly ?string $code = null,
    ) {
    }

    public static function success(string $message, ?int $id = null): self
    {
        return new self(true, $message, $id);
    }

    public static function failure(string $message, string $code = 'validation'): self
    {
        return new self(false, $message, null, $code);
    }
}
