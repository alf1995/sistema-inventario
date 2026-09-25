<?php

declare(strict_types=1);

namespace App\Modules\Authentication\Infrastructure\Persistence;

use App\Modules\Authentication\Domain\Entities\User;
use App\Modules\Authentication\Domain\Repositories\UserRepositoryInterface;
use CodeIgniter\Database\BaseConnection;
use DateTimeImmutable;

final class DatabaseUserRepository implements UserRepositoryInterface
{
    public function __construct(private readonly BaseConnection $db)
    {
    }

    public function findByLogin(string $login): ?User
    {
        $row = $this->db->table('users')
            ->groupStart()
                ->where('username', $login)
                ->orWhere('email', $login)
            ->groupEnd()
            ->limit(1)
            ->get()
            ->getRowArray();

        return $row === null ? null : $this->hydrate($row);
    }

    public function findById(int $id): ?User
    {
        $row = $this->db->table('users')
            ->where('id', $id)
            ->limit(1)
            ->get()
            ->getRowArray();

        return $row === null ? null : $this->hydrate($row);
    }

    public function touchLastActivity(int $userId, DateTimeImmutable $at): void
    {
        $this->db->table('users')
            ->where('id', $userId)
            ->update([
                'last_activity' => $at->format('Y-m-d H:i:s'),
                'updated_at'    => $at->format('Y-m-d H:i:s'),
            ]);
    }

    public function updatePasswordHash(int $userId, string $passwordHash): void
    {
        $this->db->table('users')
            ->where('id', $userId)
            ->update([
                'password_hash' => $passwordHash,
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);
    }

    public function setPasswordHash(int $userId, string $passwordHash, bool $mustChangePassword): void
    {
        $now = date('Y-m-d H:i:s');

        $this->db->table('users')
            ->where('id', $userId)
            ->update([
                'password_hash'        => $passwordHash,
                'must_change_password' => $mustChangePassword ? 1 : 0,
                'password_changed_at'  => $now,
                'updated_at'           => $now,
            ]);
    }

    
    private function hydrate(array $row): User
    {
        return new User(
            (int) $row['id'],
            (string) $row['username'],
            (string) $row['email'],
            (string) $row['password_hash'],
            (string) $row['status'],
            (bool) ($row['must_change_password'] ?? false),
            $this->rolesForUser((int) $row['id']),
        );
    }

    
    private function rolesForUser(int $userId): array
    {
        $rows = $this->db->table('user_roles ur')
            ->select('r.name')
            ->join('roles r', 'r.id = ur.role_id')
            ->where('ur.user_id', $userId)
            ->where('r.is_active', 1)
            ->orderBy('r.id', 'ASC')
            ->get()
            ->getResultArray();

        return array_values(array_map(
            static fn (array $row): string => (string) $row['name'],
            $rows,
        ));
    }
}
