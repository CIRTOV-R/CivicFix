<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

final class AuditService
{
    public function __construct(private readonly Request $request) {}

    public function log(
        AuditAction|string $action,
        Model $target,
        array $oldValues = [],
        array $newValues = [],
        ?User $actor = null,
    ): AuditLog {
        $actor ??= $this->request->user();
        $actionValue = $action instanceof AuditAction ? $action->value : $action;

        return AuditLog::create([
            'user_id' => $actor?->id,
            'incident_id' => $target instanceof Incident ? $target->id : ($target->incident_id ?? null),
            'action_type' => $actionValue,
            'target_table' => $target->getTable(),
            'target_id' => (int) $target->getKey(),
            'old_values' => $oldValues === [] ? null : $oldValues,
            'new_values' => $newValues === [] ? null : $newValues,
            'ip_address' => $this->request->ip() ?? '0.0.0.0',
            'user_agent' => $this->request->userAgent(),
            'created_at' => now(),
        ]);
    }
}
