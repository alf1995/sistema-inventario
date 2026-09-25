<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Services;

final class InventoryOperationResult
{
    private function __construct(
        public readonly bool $success,
        public readonly string $message,
        public readonly ?int $id = null,
    ) {
    }

    public static function success(string $message, ?int $id = null): self
    {
        return new self(true, $message, $id);
    }

    public static function failure(string $message): self
    {
        return new self(false, $message);
    }
}
