<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Writes append-only audit trail entries (Part 30). Never logs passwords, API tokens/secrets,
 * or other sensitive credentials — callers must only pass business-relevant before/after state.
 */
class AuditLogger
{
    public function log(
        string $action,
        ?Model $auditable = null,
        ?array $before = null,
        ?array $after = null,
        ?int $organizationId = null,
        ?int $userId = null,
    ): AuditLog {
        $request = request();

        return AuditLog::create([
            'user_id' => $userId ?? auth()->id(),
            'organization_id' => $organizationId,
            'action' => $action,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'before' => $before,
            'after' => $after,
            'ip_address' => $request instanceof Request ? $request->ip() : null,
        ]);
    }
}
