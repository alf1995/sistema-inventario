<?php

declare(strict_types=1);

namespace App\Modules\Administration\Application\Services;

use App\Shared\Application\Ports\SecurityAuditLoggerInterface;
use App\Modules\Administration\Domain\Repositories\UserAdministrationRepositoryInterface;
use App\Modules\Authentication\Application\Ports\PasswordVerifierInterface;
use App\Modules\Authentication\Application\Ports\SensitiveActionLimiterInterface;
use App\Modules\Authentication\Domain\Services\PasswordPolicy;
use App\Modules\Authentication\Domain\Repositories\ActiveSessionRepositoryInterface;
use App\Modules\Authentication\Domain\Repositories\AuthorizationRepositoryInterface;
use App\Modules\Authentication\Domain\Repositories\UserRepositoryInterface;
use DateInterval;
use DateTimeImmutable;
use Throwable;

final class UserAdministrationService
{
    private const ADMIN_ROLE = 'administrador';

    public function __construct(
        private readonly UserAdministrationRepositoryInterface $users,
        private readonly UserRepositoryInterface $authUsers,
        private readonly ActiveSessionRepositoryInterface $sessions,
        private readonly AuthorizationRepositoryInterface $authorization,
        private readonly PasswordVerifierInterface $passwords,
        private readonly PasswordPolicy $passwordPolicy,
        private readonly SensitiveActionLimiterInterface $sensitiveLimiter,
        private readonly SecurityAuditLoggerInterface $audit,
        private readonly int $inactivityTimeout,
        private readonly int $usernameMinLength,
        private readonly int $usernameMaxLength,
        private readonly int $emailMaxLength,
    ) {
    }

    
    public function users(): array
    {
        $now = new DateTimeImmutable();
        $inactiveBefore = $now->sub(new DateInterval('PT' . $this->inactivityTimeout . 'S'));
        $this->sessions->deactivateExpired($now, $inactiveBefore);

        return $this->users->allUsers();
    }

    
    public function roles(): array
    {
        return $this->users->activeRoles();
    }

    
    public function user(int $userId): ?array
    {
        return $this->users->findUser($userId);
    }

    
    public function create(
        int $actorUserId,
        string $username,
        string $email,
        string $initialPassword,
        string $status,
        array $roleIds,
        string $ipAddress,
        string $userAgent,
    ): AdministrationResult {
        if (! $this->authorization->userHasModulePermission($actorUserId, 'users', 'create')) {
            return AdministrationResult::failure('No tienes permiso para gestionar usuarios.', 'forbidden');
        }

        $username = mb_strtolower(trim($username));
        $email    = mb_strtolower(trim($email));
        $status   = trim($status);
        $roleIds  = $this->normalizeIds($roleIds);

        $identityError = $this->validateIdentity($username, $email);
        if ($identityError !== null) {
            return AdministrationResult::failure($identityError);
        }

        if (! in_array($status, ['active', 'inactive'], true)) {
            return AdministrationResult::failure('El estado seleccionado no es válido.');
        }

        if ($this->users->usernameExists($username)) {
            return AdministrationResult::failure('El nombre de usuario ya se encuentra registrado.');
        }

        if ($this->users->emailExists($email)) {
            return AdministrationResult::failure('El email ya se encuentra registrado.');
        }

        if ($roleIds === []) {
            return AdministrationResult::failure('Debes asignar al menos un rol activo al usuario.');
        }

        if (! $this->users->roleIdsAreValid($roleIds)) {
            return AdministrationResult::failure('Uno o más roles seleccionados no son válidos.');
        }

        $passwordError = $this->passwordPolicy->validate($initialPassword);
        if ($passwordError !== null) {
            return AdministrationResult::failure($passwordError);
        }

        try {
            $userId = $this->users->createUser([
                'username'      => $username,
                'email'         => $email,
                'password_hash' => $this->passwords->hash($initialPassword),
                'status'               => $status,
                'must_change_password' => 1,
                'password_changed_at'  => null,
            ], $roleIds);
        } catch (Throwable) {
            return AdministrationResult::failure('No se pudo crear el usuario. Verifica que usuario y email no estén duplicados.', 'database');
        }

        $this->audit->log($actorUserId, $userId, 'user.created', $ipAddress, $userAgent, [
            'status' => $status,
        ]);

        return AdministrationResult::success('Usuario creado correctamente.', $userId);
    }

    
    public function update(
        int $actorUserId,
        int $targetUserId,
        string $username,
        string $email,
        string $status,
        array $roleIds,
        string $ipAddress,
        string $userAgent,
    ): AdministrationResult {
        if (! $this->authorization->userHasModulePermission($actorUserId, 'users', 'edit')) {
            return AdministrationResult::failure('No tienes permiso para gestionar usuarios.', 'forbidden');
        }

        $current = $this->users->findUser($targetUserId);
        if ($current === null) {
            return AdministrationResult::failure('El usuario no existe.', 'not_found');
        }

        $username = mb_strtolower(trim($username));
        $email    = mb_strtolower(trim($email));
        $status   = trim($status);
        $roleIds  = $this->normalizeIds($roleIds);

        $identityError = $this->validateIdentity($username, $email);
        if ($identityError !== null) {
            return AdministrationResult::failure($identityError);
        }

        if (! in_array($status, ['active', 'inactive'], true)) {
            return AdministrationResult::failure('El estado seleccionado no es válido.');
        }

        if ($actorUserId === $targetUserId) {
            $status  = (string) $current['status'];
            $roleIds = array_map('intval', (array) $current['role_ids']);
        }

        if ($this->users->usernameExists($username, $targetUserId)) {
            return AdministrationResult::failure('El nombre de usuario ya se encuentra registrado.');
        }

        if ($this->users->emailExists($email, $targetUserId)) {
            return AdministrationResult::failure('El email ya se encuentra registrado.');
        }

        if ($roleIds === []) {
            return AdministrationResult::failure('Debes asignar al menos un rol activo al usuario.');
        }

        if (! $this->users->roleIdsAreValid($roleIds)) {
            return AdministrationResult::failure('Uno o más roles seleccionados no son válidos.');
        }

        if ($this->wouldRemoveLastAdministrator($targetUserId, $status, $roleIds)) {
            return AdministrationResult::failure('La operación dejaría el sistema sin un Administrador activo.');
        }

        try {
            $this->users->updateUser($targetUserId, [
                'username' => $username,
                'email'    => $email,
                'status'   => $status,
            ], $roleIds);
        } catch (Throwable) {
            return AdministrationResult::failure('No se pudo actualizar el usuario.', 'database');
        }

        if ($status !== 'active') {
            $this->sessions->deactivateAllForUser($targetUserId);
        }

        $this->audit->log($actorUserId, $targetUserId, 'user.updated', $ipAddress, $userAgent, [
            'status' => $status,
        ]);

        return AdministrationResult::success('Usuario actualizado correctamente.', $targetUserId);
    }

    public function resetPassword(
        int $actorUserId,
        int $targetUserId,
        string $actorCurrentPassword,
        string $newPassword,
        string $ipAddress,
        string $userAgent,
    ): AdministrationResult {
        if (! $this->authorization->userHasModulePermission($actorUserId, 'users', 'edit')) {
            return AdministrationResult::failure('No tienes permiso para gestionar usuarios.', 'forbidden');
        }

        if ($actorUserId === $targetUserId) {
            return AdministrationResult::failure('Usa la opción "Mi contraseña" para cambiar tu propia contraseña.');
        }

        if ($this->sensitiveLimiter->isBlocked('admin_password_reset', $actorUserId, $ipAddress)) {
            return AdministrationResult::failure(
                'Demasiados intentos de confirmación fallidos. Intenta nuevamente más tarde.',
                'rate_limited',
            );
        }

        $actor  = $this->authUsers->findById($actorUserId);
        $target = $this->authUsers->findById($targetUserId);

        if ($actor === null || ! $actor->isActive()) {
            return AdministrationResult::failure('La sesión administrativa no es válida.', 'unauthorized');
        }

        if ($target === null) {
            return AdministrationResult::failure('El usuario objetivo no existe.', 'not_found');
        }

        if (! $this->passwords->verify($actorCurrentPassword, $actor->passwordHash)) {
            $this->sensitiveLimiter->registerFailure('admin_password_reset', $actorUserId, $ipAddress);

            return AdministrationResult::failure('Tu contraseña de administrador actual no es correcta.', 'invalid_current_password');
        }

        $this->sensitiveLimiter->clear('admin_password_reset', $actorUserId, $ipAddress);

        $passwordError = $this->passwordPolicy->validate($newPassword);
        if ($passwordError !== null) {
            return AdministrationResult::failure($passwordError);
        }

        if ($this->passwords->verify($newPassword, $target->passwordHash)) {
            return AdministrationResult::failure('La nueva contraseña debe ser diferente de la contraseña actual del usuario.');
        }

        $this->authUsers->setPasswordHash($targetUserId, $this->passwords->hash($newPassword), true);
        $this->sessions->deactivateAllForUser($targetUserId);
        $this->audit->log($actorUserId, $targetUserId, 'user.password_reset', $ipAddress, $userAgent);

        return AdministrationResult::success('Contraseña restablecida. Las sesiones del usuario fueron cerradas.');
    }

    public function revokeSessions(
        int $actorUserId,
        int $targetUserId,
        string $currentSessionToken,
        string $ipAddress,
        string $userAgent,
    ): AdministrationResult {
        if (! $this->authorization->userHasModulePermission($actorUserId, 'users', 'edit')) {
            return AdministrationResult::failure('No tienes permiso para gestionar usuarios.', 'forbidden');
        }

        if ($this->users->findUser($targetUserId) === null) {
            return AdministrationResult::failure('El usuario no existe.', 'not_found');
        }

        if ($actorUserId === $targetUserId) {
            $this->sessions->deactivateAllForUserExcept($targetUserId, hash('sha256', $currentSessionToken));
            $message = 'Se cerraron tus demás sesiones activas.';
        } else {
            $this->sessions->deactivateAllForUser($targetUserId);
            $message = 'Se cerraron todas las sesiones activas del usuario.';
        }

        $this->audit->log($actorUserId, $targetUserId, 'user.sessions_revoked', $ipAddress, $userAgent);

        return AdministrationResult::success($message);
    }

    public function delete(
        int $actorUserId,
        int $targetUserId,
        string $ipAddress,
        string $userAgent,
    ): AdministrationResult {
        if (! $this->authorization->userHasModulePermission($actorUserId, 'users', 'delete')) {
            return AdministrationResult::failure('No tienes permiso para eliminar usuarios.', 'forbidden');
        }

        if ($actorUserId === $targetUserId) {
            return AdministrationResult::failure('No puedes eliminar lógicamente tu propia cuenta.');
        }

        $current = $this->users->findUser($targetUserId);
        if ($current === null) {
            return AdministrationResult::failure('El usuario no existe.', 'not_found');
        }

        if ((string) $current['status'] !== 'active') {
            return AdministrationResult::failure('El usuario ya se encuentra inactivo.');
        }

        $roleIds = array_map('intval', (array) $current['role_ids']);
        if ($this->wouldRemoveLastAdministrator($targetUserId, 'inactive', $roleIds)) {
            return AdministrationResult::failure('La operación dejaría el sistema sin un Administrador activo.');
        }

        try {
            $this->users->deactivateUser($targetUserId);
            $this->sessions->deactivateAllForUser($targetUserId);
        } catch (Throwable) {
            return AdministrationResult::failure('No se pudo eliminar lógicamente el usuario.', 'database');
        }

        $this->audit->log($actorUserId, $targetUserId, 'user.deleted_logically', $ipAddress, $userAgent);

        return AdministrationResult::success('Usuario eliminado lógicamente y sesiones cerradas.');
    }

    private function validateIdentity(string $username, string $email): ?string
    {
        $usernameLength = mb_strlen($username);

        if (
            $usernameLength < $this->usernameMinLength
            || $usernameLength > $this->usernameMaxLength
            || ! preg_match('/^[a-z0-9._-]+$/', $username)
        ) {
            return 'El usuario debe tener entre '
                . $this->usernameMinLength
                . ' y '
                . $this->usernameMaxLength
                . ' caracteres y solo puede usar letras, números, punto, guion y guion bajo.';
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > $this->emailMaxLength) {
            return 'El email no es válido o supera los ' . $this->emailMaxLength . ' caracteres.';
        }

        return null;
    }

    
    private function wouldRemoveLastAdministrator(int $targetUserId, string $newStatus, array $roleIds): bool
    {
        if (! $this->users->userHasRoleSlug($targetUserId, self::ADMIN_ROLE)) {
            return false;
        }

        $adminRoleId = null;
        foreach ($this->users->activeRoles() as $role) {
            if ((string) $role['slug'] === self::ADMIN_ROLE) {
                $adminRoleId = (int) $role['id'];
                break;
            }
        }

        $keepsAdminRole = $adminRoleId !== null && in_array($adminRoleId, $roleIds, true);
        $keepsActive    = $newStatus === 'active';

        if ($keepsAdminRole && $keepsActive) {
            return false;
        }

        return $this->users->countActiveUsersWithRoleSlugExcluding(self::ADMIN_ROLE, $targetUserId) === 0;
    }

    
    private function normalizeIds(array $ids): array
    {
        $normalized = array_map('intval', $ids);
        $normalized = array_filter($normalized, static fn (int $id): bool => $id > 0);

        return array_values(array_unique($normalized));
    }
}
