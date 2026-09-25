<?php

declare(strict_types=1);

namespace App\Modules\Authentication\Infrastructure\Security;

use App\Modules\Authentication\Application\Ports\PasswordVerifierInterface;
use RuntimeException;

final class NativePasswordVerifier implements PasswordVerifierInterface
{
    public function verify(string $plainPassword, string $passwordHash): bool
    {
        return password_verify($plainPassword, $passwordHash);
    }

    public function needsRehash(string $passwordHash): bool
    {
        return password_needs_rehash($passwordHash, PASSWORD_DEFAULT);
    }

    public function hash(string $plainPassword): string
    {
        $hash = password_hash($plainPassword, PASSWORD_DEFAULT);

        if ($hash === false) {
            throw new RuntimeException('No se pudo generar el hash de la contraseña.');
        }

        return $hash;
    }
}
