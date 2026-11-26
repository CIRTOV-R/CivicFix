<?php

declare(strict_types=1);

namespace App\Actions\Incidents;

use App\Enums\AuditAction;
use App\Enums\IncidentStatus;
use App\Models\Incident;
use App\Services\AuditService;
use App\Services\IncidentStateMachine;
use DomainException;
use Illuminate\Support\Facades\DB;

final class StartIncidentAction
{
    public function __construct(
        private readonly AuditService $auditService,
        private readonly IncidentStateMachine $stateMachine,
    ) {}

    public function execute(Incident $incident, float $latitude, float $longitude): Incident
    {
        if ($incident->crew_id === null) {
            throw new DomainException('La incidencia debe estar asignada a una cuadrilla antes de iniciarla.');
        }

        $incident = DB::transaction(function () use ($incident, $latitude, $longitude): Incident {
            $this->stateMachine->transition($incident, IncidentStatus::EN_PROCESO);

            $crew = $incident->crew;
            $crew->update([
                'latitude' => $latitude,
                'longitude' => $longitude,
                'last_location_update' => now(),
            ]);

            $this->auditService->log(AuditAction::CREW_LOCATION_UPDATED, $crew, [], [
                'latitude' => $latitude,
                'longitude' => $longitude,
            ]);
            $this->auditService->log(AuditAction::INCIDENT_STATUS_CHANGED, $incident, [
                'status' => IncidentStatus::ASIGNADO->value,
            ], [
                'status' => IncidentStatus::EN_PROCESO->value,
            ]);

            return $incident->load(['crew', 'category']);
        });

        return $incident;
    }
}
