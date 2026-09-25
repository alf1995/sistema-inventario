<?php

declare(strict_types=1);

namespace App\Modules\Authentication\Domain\Services;

use InvalidArgumentException;

final class PasswordPolicy
{
    public function __construct(
        private readonly int $minLength,
        private readonly int $maxLength,
        private readonly bool $requireUppercase,
        private readonly bool $requireLowercase,
        private readonly bool $requireNumber,
        private readonly bool $requireSpecial,
    ) {
        if ($this->minLength < 1) {
            throw new InvalidArgumentException('passwordMinLength debe ser mayor que cero.');
        }

        if ($this->maxLength < $this->minLength) {
            throw new InvalidArgumentException('passwordMaxLength no puede ser menor que passwordMinLength.');
        }
    }

    public function validate(string $password): ?string
    {
        $length = mb_strlen($password);

        if ($length < $this->minLength) {
            return 'La nueva contraseña debe tener al menos ' . $this->minLength . ' caracteres.';
        }

        if ($length > $this->maxLength) {
            return 'La nueva contraseña no puede superar los ' . $this->maxLength . ' caracteres.';
        }

        $missing = [];

        if ($this->requireUppercase && ! preg_match('/[A-Z]/u', $password)) {
            $missing[] = 'una mayúscula';
        }

        if ($this->requireLowercase && ! preg_match('/[a-z]/u', $password)) {
            $missing[] = 'una minúscula';
        }

        if ($this->requireNumber && ! preg_match('/\d/u', $password)) {
            $missing[] = 'un número';
        }

        if ($this->requireSpecial && ! preg_match('/[^A-Za-z0-9]/u', $password)) {
            $missing[] = 'un carácter especial';
        }

        if ($missing !== []) {
            return 'La nueva contraseña debe incluir ' . implode(', ', $missing) . '.';
        }

        return null;
    }

    public function description(): string
    {
        $requirements = [
            'entre ' . $this->minLength . ' y ' . $this->maxLength . ' caracteres',
        ];

        if ($this->requireUppercase) {
            $requirements[] = 'una mayúscula';
        }

        if ($this->requireLowercase) {
            $requirements[] = 'una minúscula';
        }

        if ($this->requireNumber) {
            $requirements[] = 'un número';
        }

        if ($this->requireSpecial) {
            $requirements[] = 'un carácter especial';
        }

        return ucfirst(implode(', ', $requirements)) . '.';
    }
}
