<?php

declare(strict_types=1);

namespace App\Modules\Authentication\Infrastructure\Persistence;

use App\Modules\Authentication\Domain\Entities\ActiveSession;
use App\Modules\Authentication\Domain\Repositories\ActiveSessionRepositoryInterface;
use CodeIgniter\Database\BaseConnection;
use DateTimeImmutable;

final class DatabaseActiveSessionRepository implements ActiveSessionRepositoryInterface
{
    public function __construct(private readonly BaseConnection $db)
    {
    }

    public function deactivateExpiredForUser(int $userId, DateTimeImmutable $now, DateTimeImmutable $inactiveBefore): void
    {
        $this->db->table('user_sessions')
            ->where('user_id', $userId)
            ->where('is_active', 1)
            ->groupStart()
                ->where('expires_at <=', $now->format('Y-m-d H:i:s'))
                ->orWhere('last_activity <=', $inactiveBefore->format('Y-m-d H:i:s'))
            ->groupEnd()
            ->update([
                'is_active' => 0,
                'updated_at' => $now->format('Y-m-d H:i:s'),
            ]);
    }

    public function deactivateExpired(DateTimeImmutable $now, DateTimeImmutable $inactiveBefore): void
    {
        $this->db->table('user_sessions')
            ->where('is_active', 1)
            ->groupStart()
                ->where('expires_at <=', $now->format('Y-m-d H:i:s'))
                ->orWhere('last_activity <=', $inactiveBefore->format('Y-m-d H:i:s'))
            ->groupEnd()
            ->update([
                'is_active'  => 0,
                'updated_at' => $now->format('Y-m-d H:i:s'),
            ]);
    }

    public function countActiveForUser(int $userId): int
    {
        return $this->db->table('user_sessions')
            ->where('user_id', $userId)
            ->where('is_active', 1)
            ->countAllResults();
    }

    public function create(
        int $userId,
        string $tokenHash,
        string $ipAddress,
        string $userAgent,
        DateTimeImmutable $lastActivity,
        DateTimeImmutable $expiresAt,
    ): void {
        $now = $lastActivity->format('Y-m-d H:i:s');

        $this->db->table('user_sessions')->insert([
            'user_id'       => $userId,
            'token_hash'    => $tokenHash,
            'ip_address'    => mb_substr($ipAddress, 0, 45),
            'user_agent'    => $userAgent,
            'last_activity' => $now,
            'expires_at'    => $expiresAt->format('Y-m-d H:i:s'),
            'is_active'     => 1,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);
    }

    public function findActive(int $userId, string $tokenHash): ?ActiveSession
    {
        $row = $this->db->table('user_sessions')
            ->where('user_id', $userId)
            ->where('token_hash', $tokenHash)
            ->where('is_active', 1)
            ->limit(1)
            ->get()
            ->getRowArray();

        if ($row === null) {
            return null;
        }

        return new ActiveSession(
            (int) $row['id'],
            (int) $row['user_id'],
            (string) $row['token_hash'],
            new DateTimeImmutable((string) $row['last_activity']),
            new DateTimeImmutable((string) $row['expires_at']),
            (bool) $row['is_active'],
        );
    }

    public function touch(int $sessionId, DateTimeImmutable $lastActivity, DateTimeImmutable $expiresAt): void
    {
        $this->db->table('user_sessions')
            ->where('id', $sessionId)
            ->where('is_active', 1)
            ->update([
                'last_activity' => $lastActivity->format('Y-m-d H:i:s'),
                'expires_at'    => $expiresAt->format('Y-m-d H:i:s'),
                'updated_at'    => $lastActivity->format('Y-m-d H:i:s'),
            ]);
    }

    public function deactivate(int $userId, string $tokenHash): void
    {
        $this->db->table('user_sessions')
            ->where('user_id', $userId)
            ->where('token_hash', $tokenHash)
            ->update([
                'is_active'  => 0,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    public function deactivateAllForUser(int $userId): void
    {
        $this->db->table('user_sessions')
            ->where('user_id', $userId)
            ->where('is_active', 1)
            ->update([
                'is_active'  => 0,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    public function deactivateAllForUserExcept(int $userId, string $tokenHashToKeep): void
    {
        $this->db->table('user_sessions')
            ->where('user_id', $userId)
            ->where('is_active', 1)
            ->where('token_hash !=', $tokenHashToKeep)
            ->update([
                'is_active'  => 0,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }
}
