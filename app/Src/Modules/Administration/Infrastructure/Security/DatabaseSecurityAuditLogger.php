<?php

declare(strict_types=1);

namespace App\Modules\Administration\Infrastructure\Security;

use App\Shared\Application\Ports\SecurityAuditLoggerInterface;
use CodeIgniter\Database\BaseConnection;
use JsonException;
use Throwable;

final class DatabaseSecurityAuditLogger implements SecurityAuditLoggerInterface
{
    public function __construct(private readonly BaseConnection $db)
    {
    }

    public function log(
        ?int $actorUserId,
        ?int $targetUserId,
        string $action,
        string $ipAddress,
        string $userAgent,
        array $metadata = [],
    ): void {
        try {
            $encoded = $metadata === [] ? null : json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (JsonException) {
            $encoded = null;
        }

        try {
            $this->db->table('auth_audit_logs')->insert([
                'actor_user_id'  => $actorUserId,
                'target_user_id' => $targetUserId,
                'action'         => mb_substr($action, 0, 100),
                'ip_address'     => mb_substr($ipAddress, 0, 45),
                'user_agent'     => mb_substr($userAgent, 0, 255),
                'metadata'       => $encoded,
                'created_at'     => date('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $e) {
            log_message('error', 'No se pudo persistir auditoría de seguridad para la acción {action}: {message}', [
                'action'  => $action,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
