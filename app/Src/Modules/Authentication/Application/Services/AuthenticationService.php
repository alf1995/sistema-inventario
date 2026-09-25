<?php

declare(strict_types=1);

namespace App\Modules\Authentication\Application\Services;

use App\Modules\Authentication\Application\Ports\LoginAttemptLimiterInterface;
use App\Modules\Authentication\Application\Ports\PasswordVerifierInterface;
use App\Modules\Authentication\Domain\Repositories\ActiveSessionRepositoryInterface;
use App\Modules\Authentication\Domain\Repositories\UserRepositoryInterface;
use DateInterval;
use DateTimeImmutable;

final class AuthenticationService
{
    private const DUMMY_HASH = '$2y$12$/j2L8YEG26PpIShcyVvYZ.7/q1ze0Yyki8mOEe5cdUx5TB7cpfHSW';

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly ActiveSessionRepositoryInterface $sessions,
        private readonly PasswordVerifierInterface $passwords,
        private readonly LoginAttemptLimiterInterface $limiter,
        private readonly int $inactivityTimeout,
        private readonly int $maxActiveSessions,
    ) {
    }

    public function authenticate(string $login, string $password, string $ipAddress, string $userAgent): AuthenticationResult
    {
        $login = $this->normalizeLogin($login);

        if ($this->limiter->isBlocked($ipAddress, $login)) {
            $retryAfter = max(1, $this->limiter->retryAfter());

            return AuthenticationResult::failure(
                'rate_limited',
                "Demasiados intentos fallidos. Intenta nuevamente en {$retryAfter} segundo(s).",
                $retryAfter,
            );
        }

        $user = $this->users->findByLogin($login);
        $hash = $user?->passwordHash ?? self::DUMMY_HASH;

        if (! $this->passwords->verify($password, $hash)) {
            $this->limiter->registerFailure($ipAddress, $login);

            return AuthenticationResult::failure('invalid_credentials', 'Usuario/email o contraseña incorrectos.');
        }

        if ($user === null) {
            $this->limiter->registerFailure($ipAddress, $login);

            return AuthenticationResult::failure('invalid_credentials', 'Usuario/email o contraseña incorrectos.');
        }

        if (! $user->isActive()) {
            return AuthenticationResult::failure('inactive_user', 'La cuenta se encuentra inactiva.');
        }

        $this->limiter->clear($ipAddress, $login);

        if ($this->passwords->needsRehash($user->passwordHash)) {
            $this->users->updatePasswordHash($user->id, $this->passwords->hash($password));
        }

        $now            = new DateTimeImmutable();
        $inactiveBefore = $now->sub(new DateInterval('PT' . $this->inactivityTimeout . 'S'));

        $this->sessions->deactivateExpiredForUser($user->id, $now, $inactiveBefore);

        if ($this->sessions->countActiveForUser($user->id) >= $this->maxActiveSessions) {
            return AuthenticationResult::failure(
                'session_limit',
                'Se alcanzó el límite de sesiones activas para este usuario. Cierra otra sesión e intenta nuevamente.',
            );
        }

        $plainToken = bin2hex(random_bytes(32));
        $tokenHash  = hash('sha256', $plainToken);
        $expiresAt  = $now->add(new DateInterval('PT' . $this->inactivityTimeout . 'S'));

        $this->sessions->create(
            $user->id,
            $tokenHash,
            $ipAddress,
            mb_substr($userAgent, 0, 255),
            $now,
            $expiresAt,
        );
        $this->users->touchLastActivity($user->id, $now);

        return AuthenticationResult::success($user, $plainToken);
    }

    public function validateSession(int $userId, string $plainToken): SessionValidationResult
    {
        if ($userId <= 0 || $plainToken === '') {
            return SessionValidationResult::invalid('missing_session');
        }

        $tokenHash = hash('sha256', $plainToken);
        $session   = $this->sessions->findActive($userId, $tokenHash);

        if ($session === null) {
            return SessionValidationResult::invalid('invalid_session');
        }

        $now            = new DateTimeImmutable();
        $inactiveBefore = $now->sub(new DateInterval('PT' . $this->inactivityTimeout . 'S'));

        if ($session->expiresAt <= $now || $session->lastActivity <= $inactiveBefore) {
            $this->sessions->deactivate($userId, $tokenHash);

            return SessionValidationResult::invalid('expired_session');
        }

        $user = $this->users->findById($userId);

        if ($user === null || ! $user->isActive()) {
            $this->sessions->deactivate($userId, $tokenHash);

            return SessionValidationResult::invalid('inactive_user');
        }

        $expiresAt = $now->add(new DateInterval('PT' . $this->inactivityTimeout . 'S'));
        $this->sessions->touch($session->id, $now, $expiresAt);
        $this->users->touchLastActivity($userId, $now);

        return SessionValidationResult::valid($user);
    }

    public function logout(int $userId, string $plainToken): void
    {
        if ($userId <= 0 || $plainToken === '') {
            return;
        }

        $this->sessions->deactivate($userId, hash('sha256', $plainToken));
    }

    private function normalizeLogin(string $login): string
    {
        return mb_strtolower(trim($login));
    }
}
