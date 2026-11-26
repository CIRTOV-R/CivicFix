<?php

declare(strict_types=1);

namespace App\Actions\Incidents;

use App\Enums\AuditAction;
use App\Enums\MediaCollection;
use App\Jobs\AssignOptimalCrewJob;
use App\Models\Category;
use App\Models\Incident;
use App\Models\User;
use App\Services\AuditService;
use App\Services\MediaService;
use App\Services\SpatialService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateIncidentAction
{
    public function __construct(
        private readonly AuditService $auditService,
        private readonly MediaService $mediaService,
        private readonly SpatialService $spatialService,
    ) {}

    public function execute(
        array $data,
        ?UploadedFile $beforePhoto,
        User $citizen,
        string $idempotencyKey,
    ): Incident {
        $category = Category::findOrFail($data['category_id']);

        $incident = DB::transaction(function () use ($data, $beforePhoto, $citizen, $idempotencyKey, $category): Incident {
            $incident = Incident::create([
                'uuid' => Str::uuid()->toString(),
                'idempotency_key' => $idempotencyKey,
                'citizen_id' => $citizen->id,
                'category_id' => $category->id,
                'parish_id' => $this->spatialService->nearestParish(
                    (float) $data['latitude'],
                    (float) $data['longitude'],
                )?->id,
                'title' => $data['title'],
                'description' => $data['description'],
                'address_reference' => $data['address_reference'] ?? null,
                'priority' => $category->default_priority,
                'status' => 'REPORTADO',
                'latitude' => (float) $data['latitude'],
                'longitude' => (float) $data['longitude'],
                'sla_due_at' => now()->addHours($category->sla_resolution_hours),
            ]);

            $this->mediaService->attach($incident, $beforePhoto, MediaCollection::BEFORE_REPAIR);
            $this->auditService->log(AuditAction::INCIDENT_CREATED, $incident, [], [
                'uuid' => $incident->uuid,
                'status' => $incident->status->value,
                'priority' => $incident->priority->value,
                'latitude' => $incident->latitude,
                'longitude' => $incident->longitude,
            ], $citizen);

            return $incident;

        });

        AssignOptimalCrewJob::dispatch($incident)->afterCommit();

        return $incident->load(['category', 'citizen', 'parish']);
    }
}
