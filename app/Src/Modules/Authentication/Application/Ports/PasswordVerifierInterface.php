<?php

declare(strict_types=1);

namespace App\Modules\Authentication\Application\Ports;

interface PasswordVerifierInterface
{
    public function verify(string $plainPassword, string $passwordHash): bool;

    public function needsRehash(string $passwordHash): bool;

    public function hash(string $plainPassword): string;
}
