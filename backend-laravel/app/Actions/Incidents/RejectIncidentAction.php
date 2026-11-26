<?php

declare(strict_types=1);

namespace App\Actions\Incidents;

use App\Enums\AuditAction;
use App\Enums\IncidentStatus;
use App\Events\IncidentRejectedEvent;
use App\Models\Incident;
use App\Services\AuditService;
use App\Services\IncidentStateMachine;
use Illuminate\Support\Facades\DB;

final class RejectIncidentAction
{
    public function __construct(
        private readonly AuditService $auditService,
        private readonly IncidentStateMachine $stateMachine,
    ) {}

    public function execute(Incident $incident, string $reason): Incident
    {
        $previousStatus = $incident->status;
        $incident = DB::transaction(function () use ($incident, $reason, $previousStatus): Incident {
            $this->stateMachine->transition($incident, IncidentStatus::RECHAZADO, [
                'rejection_reason' => $reason,
            ]);

            $this->auditService->log(AuditAction::INCIDENT_REJECTED, $incident, [
                'status' => $previousStatus->value,
            ], [
                'status' => IncidentStatus::RECHAZADO->value,
                'reason' => $reason,
            ]);

            return $incident->refresh();
        });

        event(new IncidentRejectedEvent($incident, $reason));

        return $incident;
    }
}
