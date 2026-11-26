<?php

declare(strict_types=1);

namespace App\Actions\Incidents;

use App\Enums\AuditAction;
use App\Enums\IncidentStatus;
use App\Events\IncidentAssignedEvent;
use App\Models\Crew;
use App\Models\Incident;
use App\Services\AuditService;
use App\Services\IncidentStateMachine;
use Illuminate\Support\Facades\DB;

final class AssignManualCrewAction
{
    public function __construct(
        private readonly AuditService $auditService,
        private readonly IncidentStateMachine $stateMachine,
    ) {}

    public function execute(Incident $incident, Crew $crew): Incident
    {
        $oldValues = [
            'status' => $incident->status->value,
            'crew_id' => $incident->crew_id,
        ];

        $incident = DB::transaction(function () use ($incident, $crew, $oldValues): Incident {
            if ($incident->status === IncidentStatus::REPORTADO) {
                $this->stateMachine->transition($incident, IncidentStatus::ASIGNADO, ['crew_id' => $crew->id]);
            } else {
                $incident->update(['crew_id' => $crew->id]);
            }

            $crew->increment('current_load');
            $this->auditService->log(AuditAction::INCIDENT_ASSIGNED, $incident, [
                'status' => $oldValues['status'],
                'crew_id' => $oldValues['crew_id'],
            ], [
                'status' => $incident->status->value,
                'crew_id' => $crew->id,
                'mode' => 'manual',
            ]);

            return $incident->refresh();
        });

        event(new IncidentAssignedEvent($incident, $crew, ['source' => 'manual']));

        return $incident;
    }
}
