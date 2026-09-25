<?php

declare(strict_types=1);

namespace App\Modules\Authentication\Application\Services;

use App\Shared\Application\Ports\SecurityAuditLoggerInterface;
use App\Modules\Authentication\Application\Ports\PasswordVerifierInterface;
use App\Modules\Authentication\Application\Ports\SensitiveActionLimiterInterface;
use App\Modules\Authentication\Domain\Services\PasswordPolicy;
use App\Modules\Authentication\Domain\Repositories\ActiveSessionRepositoryInterface;
use App\Modules\Authentication\Domain\Repositories\UserRepositoryInterface;

final class AccountPasswordService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly ActiveSessionRepositoryInterface $sessions,
        private readonly PasswordVerifierInterface $passwords,
        private readonly PasswordPolicy $passwordPolicy,
        private readonly SensitiveActionLimiterInterface $limiter,
        private readonly SecurityAuditLoggerInterface $audit,
    ) {
    }

    public function change(
        int $userId,
        string $currentPassword,
        string $newPassword,
        string $currentSessionToken,
        string $ipAddress,
        string $userAgent,
    ): AccountPasswordResult {
        if ($this->limiter->isBlocked('change_password', $userId, $ipAddress)) {
            return AccountPasswordResult::failure('Demasiados intentos fallidos. Intenta nuevamente más tarde.', 'rate_limited');
        }

        $user = $this->users->findById($userId);
        if ($user === null || ! $user->isActive()) {
            return AccountPasswordResult::failure('La cuenta ya no está disponible.', 'unauthorized');
        }

        if (! $this->passwords->verify($currentPassword, $user->passwordHash)) {
            $this->limiter->registerFailure('change_password', $userId, $ipAddress);

            return AccountPasswordResult::failure('La contraseña actual no es correcta.', 'invalid_current_password');
        }

        $this->limiter->clear('change_password', $userId, $ipAddress);

        $passwordError = $this->passwordPolicy->validate($newPassword);
        if ($passwordError !== null) {
            return AccountPasswordResult::failure($passwordError);
        }

        if ($this->passwords->verify($newPassword, $user->passwordHash)) {
            return AccountPasswordResult::failure('La nueva contraseña debe ser diferente de la contraseña actual.');
        }

        $this->users->setPasswordHash($userId, $this->passwords->hash($newPassword), false);
        $this->sessions->deactivateAllForUserExcept($userId, hash('sha256', $currentSessionToken));
        $this->audit->log($userId, $userId, 'account.password_changed', $ipAddress, $userAgent);

        return AccountPasswordResult::success('Contraseña actualizada correctamente. Las demás sesiones fueron cerradas.');
    }
}
