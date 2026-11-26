<?php

declare(strict_types=1);

namespace App\Actions\Incidents;

use App\Enums\AuditAction;
use App\Models\Crew;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;

final class UpdateCrewLocationAction
{
    public function __construct(private readonly AuditService $auditService) {}

    public function execute(Crew $crew, float $latitude, float $longitude): Crew
    {
        return DB::transaction(function () use ($crew, $latitude, $longitude): Crew {
            $oldValues = [
                'latitude' => $crew->latitude,
                'longitude' => $crew->longitude,
            ];

            $crew->update([
                'latitude' => $latitude,
                'longitude' => $longitude,
                'last_location_update' => now(),
            ]);

            $this->auditService->log(AuditAction::CREW_LOCATION_UPDATED, $crew, $oldValues, [
                'latitude' => $crew->latitude,
                'longitude' => $crew->longitude,
            ]);

            return $crew->refresh();
        });
    }
}
