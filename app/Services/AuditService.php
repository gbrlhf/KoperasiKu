<?php

namespace App\Services;

use App\Models\AuditLog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class AuditService
{
    public function record(
        string $action,
        string $entityType,
        ?string $entityId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $actorId = null,
        ?string $actorRole = null,
        ?string $ipAddress = null
    ): void {
        if (!Schema::hasTable('audit_logs')) {
            return;
        }

        $user = Auth::user();
        $resolvedActorId = $actorId ?? ($user?->id ? (string) $user->id : null);
        $resolvedRole = $actorRole ?? (session('active_role') ?: ($user ? ($user->roleCodes()[0] ?? null) : null));
        $resolvedIp = $ipAddress ?? request()?->ip();

        AuditLog::create([
            'actor_id' => $resolvedActorId,
            'actor_role' => $resolvedRole ? strtoupper((string) $resolvedRole) : null,
            'action' => strtoupper($action),
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $resolvedIp,
            'created_at' => CarbonImmutable::now('UTC'),
        ]);
    }
}
