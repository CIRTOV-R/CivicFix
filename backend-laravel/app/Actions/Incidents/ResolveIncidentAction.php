<?php

declare(strict_types=1);

namespace App\Actions\Incidents;

use App\Enums\AuditAction;
use App\Enums\IncidentStatus;
use App\Enums\MediaCollection;
use App\Events\IncidentResolvedEvent;
use App\Models\Incident;
use App\Services\AuditService;
use App\Services\IncidentStateMachine;
use App\Services\MediaService;
use App\Services\SpatialService;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

final class ResolveIncidentAction
{
    public function __construct(
        private readonly AuditService $auditService,
        private readonly IncidentStateMachine $stateMachine,
        private readonly MediaService $mediaService,
        private readonly SpatialService $spatialService,
    ) {}

    public function execute(
        Incident $incident,
        float $latitude,
        float $longitude,
        ?UploadedFile $afterPhoto,
    ): Incident {
        $radius = (int) config('civicfix.geofence_radius_meters', 50);

        if (! $this->spatialService->isWithinRadius(
            $incident->latitude,
            $incident->longitude,
            $latitude,
            $longitude,
            $radius,
        )) {
            throw new DomainException('El cierre requiere estar a 50 metros o menos del sitio del reporte.');
        }

        if ($afterPhoto === null && ! $incident->hasMedia(MediaCollection::AFTER_REPAIR->value)) {
            throw new DomainException('El cierre requiere una fotografía de evidencia posterior.');
        }

        $incident = DB::transaction(function () use ($incident, $afterPhoto): Incident {
            $this->mediaService->attach($incident, $afterPhoto, MediaCollection::AFTER_REPAIR);
            $this->stateMachine->transition($incident, IncidentStatus::RESUELTO, [
                'resolved_at' => now(),
            ]);

            $this->auditService->log(AuditAction::INCIDENT_STATUS_CHANGED, $incident, [
                'status' => IncidentStatus::EN_PROCESO->value,
            ], [
                'status' => IncidentStatus::RESUELTO->value,
                'resolved_at' => now()->toIso8601String(),
                'after_repair_evidence' => true,
            ]);

            return $incident->load(['crew', 'category', 'media']);
        });

        event(new IncidentResolvedEvent($incident));

        return $incident;
    }
}
