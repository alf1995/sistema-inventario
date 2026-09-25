<?php

declare(strict_types=1);

namespace App\Modules\Administration\Infrastructure\Persistence;

use App\Modules\Administration\Domain\Repositories\UserAdministrationRepositoryInterface;
use CodeIgniter\Database\BaseConnection;
use Throwable;

final class DatabaseUserAdministrationRepository implements UserAdministrationRepositoryInterface
{
    public function __construct(private readonly BaseConnection $db)
    {
    }

    public function allUsers(): array
    {
        $users = $this->db->table('users u')
            ->select('u.id, u.username, u.email, u.status, u.must_change_password, u.password_changed_at, u.last_activity, u.created_at')
            ->orderBy('u.id', 'ASC')
            ->get()
            ->getResultArray();

        $roleRows = $this->db->table('user_roles ur')
            ->select('ur.user_id, r.name')
            ->join('roles r', 'r.id = ur.role_id')
            ->orderBy('r.name', 'ASC')
            ->get()
            ->getResultArray();

        $rolesByUser = [];
        foreach ($roleRows as $roleRow) {
            $rolesByUser[(int) $roleRow['user_id']][] = (string) $roleRow['name'];
        }

        $sessionCounts = [];
        foreach ($this->db->table('user_sessions')
            ->select('user_id, COUNT(*) AS total', false)
            ->where('is_active', 1)
            ->groupBy('user_id')
            ->get()
            ->getResultArray() as $row) {
            $sessionCounts[(int) $row['user_id']] = (int) $row['total'];
        }

        foreach ($users as &$user) {
            $userId = (int) $user['id'];
            $user['roles'] = $rolesByUser[$userId] ?? [];
            $user['active_sessions'] = $sessionCounts[$userId] ?? 0;
        }
        unset($user);

        return $users;
    }

    public function findUser(int $userId): ?array
    {
        $row = $this->db->table('users')
            ->select('id, username, email, status, must_change_password, password_changed_at, last_activity, created_at, updated_at')
            ->where('id', $userId)
            ->limit(1)
            ->get()
            ->getRowArray();

        if ($row === null) {
            return null;
        }

        $row['role_ids'] = $this->roleIdsForUser($userId);
        $row['roles']    = $this->roleNamesForUser($userId);

        return $row;
    }

    public function activeRoles(): array
    {
        return $this->db->table('roles')
            ->select('id, name, slug, description')
            ->where('is_active', 1)
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();
    }

    public function roleIdsForUser(int $userId): array
    {
        $rows = $this->db->table('user_roles')
            ->select('role_id')
            ->where('user_id', $userId)
            ->get()
            ->getResultArray();

        return array_values(array_map(static fn (array $row): int => (int) $row['role_id'], $rows));
    }

    public function usernameExists(string $username, ?int $exceptUserId = null): bool
    {
        $builder = $this->db->table('users')->where('username', $username);
        if ($exceptUserId !== null) {
            $builder->where('id !=', $exceptUserId);
        }

        return $builder->countAllResults() > 0;
    }

    public function emailExists(string $email, ?int $exceptUserId = null): bool
    {
        $builder = $this->db->table('users')->where('email', $email);
        if ($exceptUserId !== null) {
            $builder->where('id !=', $exceptUserId);
        }

        return $builder->countAllResults() > 0;
    }

    public function roleIdsAreValid(array $roleIds): bool
    {
        $roleIds = array_values(array_unique(array_filter($roleIds, static fn (int $id): bool => $id > 0)));
        if ($roleIds === []) {
            return true;
        }

        return $this->db->table('roles')
            ->whereIn('id', $roleIds)
            ->where('is_active', 1)
            ->countAllResults() === count($roleIds);
    }

    public function createUser(array $data, array $roleIds): int
    {
        $this->db->transBegin();

        try {
            $now = date('Y-m-d H:i:s');
            $this->db->table('users')->insert($data + [
                'last_activity' => null,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
            $userId = (int) $this->db->insertID();
            $this->syncRoles($userId, $roleIds, $now);
            $this->db->transCommit();

            return $userId;
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    public function updateUser(int $userId, array $data, array $roleIds): void
    {
        $this->db->transBegin();

        try {
            $now = date('Y-m-d H:i:s');
            $this->db->table('users')
                ->where('id', $userId)
                ->update($data + ['updated_at' => $now]);
            $this->syncRoles($userId, $roleIds, $now);
            $this->db->transCommit();
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    public function userHasRoleSlug(int $userId, string $roleSlug): bool
    {
        return $this->db->table('user_roles ur')
            ->join('roles r', 'r.id = ur.role_id')
            ->where('ur.user_id', $userId)
            ->where('r.slug', $roleSlug)
            ->countAllResults() > 0;
    }

    public function countActiveUsersWithRoleSlugExcluding(string $roleSlug, int $excludedUserId): int
    {
        return $this->db->table('users u')
            ->join('user_roles ur', 'ur.user_id = u.id')
            ->join('roles r', 'r.id = ur.role_id')
            ->where('u.status', 'active')
            ->where('r.is_active', 1)
            ->where('r.slug', $roleSlug)
            ->where('u.id !=', $excludedUserId)
            ->countAllResults();
    }

    public function deactivateUser(int $userId): void
    {
        $this->db->table('users')
            ->where('id', $userId)
            ->update([
                'status' => 'inactive',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    
    private function roleNamesForUser(int $userId): array
    {
        $rows = $this->db->table('user_roles ur')
            ->select('r.name')
            ->join('roles r', 'r.id = ur.role_id')
            ->where('ur.user_id', $userId)
            ->orderBy('r.name', 'ASC')
            ->get()
            ->getResultArray();

        return array_values(array_map(static fn (array $row): string => (string) $row['name'], $rows));
    }

    
    private function syncRoles(int $userId, array $roleIds, string $now): void
    {
        $this->db->table('user_roles')->where('user_id', $userId)->delete();

        foreach (array_values(array_unique($roleIds)) as $roleId) {
            if ($roleId <= 0) {
                continue;
            }

            $this->db->table('user_roles')->insert([
                'user_id'    => $userId,
                'role_id'    => $roleId,
                'created_at' => $now,
            ]);
        }
    }
}
