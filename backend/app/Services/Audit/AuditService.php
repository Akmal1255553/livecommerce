<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Logging\StructuredLogger;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\BaseService;
use Illuminate\Http\Request;

class AuditService extends BaseService
{
    public function __construct(StructuredLogger $logger)
    {
        parent::__construct($logger);
    }

    public function record(
        ?User $actor,
        string $action,
        string $entityType,
        string $entityId,
        ?array $old = null,
        ?array $new = null,
        ?Request $request = null,
    ): AuditLog {
        /** @var AuditLog $log */
        $log = AuditLog::query()->create([
            'user_id' => $actor?->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $request?->ip(),
            'user_agent' => $request !== null ? substr($request->userAgent() ?? '', 0, 500) : null,
            'created_at' => now(),
        ]);

        return $log;
    }
}
