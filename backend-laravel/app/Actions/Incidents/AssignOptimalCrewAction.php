<?php

declare(strict_types=1);

namespace App\Actions\Incidents;

use App\Enums\AuditAction;
use App\Enums\IncidentStatus;
use App\Events\IncidentAssignedEvent;
use App\Models\Crew;
use App\Models\Incident;
use App\Services\AssignmentService;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;

final class AssignOptimalCrewAction
{
    public function __construct(
        private readonly AssignmentService $assignmentService,
        private readonly AuditService $auditService,
    ) {}

    public function execute(Incident $incident): ?Crew
    {
        if ($incident->status !== IncidentStatus::REPORTADO) {
            return $incident->crew;
        }

        $decision = $this->assignmentService->resolveOptimalCrew($incident);

        if ($decision === null) {
            return null;
        }

        $crew = DB::transaction(function () use ($incident, $decision): Crew {
            $incident->update([
                'status' => IncidentStatus::ASIGNADO,
                'crew_id' => $decision->crew->id,
            ]);

            $decision->crew->increment('current_load');

            if ($decision->error !== null) {
                $this->auditService->log(AuditAction::ROUTING_FALLBACK, $incident, [], [
                    'reason' => $decision->error,
                    'assignment' => $decision->toArray(),
                ]);
            }

            $this->auditService->log(AuditAction::INCIDENT_ASSIGNED, $incident, [
                'status' => IncidentStatus::REPORTADO->value,
                'crew_id' => null,
            ], [
                'status' => IncidentStatus::ASIGNADO->value,
                'crew_id' => $decision->crew->id,
                'assignment' => $decision->toArray(),
            ]);

            return $decision->crew;
        });

        event(new IncidentAssignedEvent($incident->refresh(), $crew, $decision->toArray()));

        return $crew;
    }
}
