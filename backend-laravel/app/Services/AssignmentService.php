<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\CrewAssignmentDecision;
use App\Models\Crew;
use App\Models\Incident;
use Illuminate\Database\Eloquent\Collection;

final class AssignmentService
{
    public function __construct(
        private readonly SpatialService $spatialService,
        private readonly RoutingClient $routingClient,
        private readonly CircuitBreaker $circuitBreaker,
    ) {}

    public function resolveOptimalCrew(Incident $incident): ?CrewAssignmentDecision
    {
        $radius = (int) config('civicfix.prefilter_radius_meters', 15000);
        $candidates = $this->spatialService->candidatesWithinRadius($incident, $radius);

        if ($candidates->isEmpty()) {
            return null;
        }

        $error = null;
        $response = null;

        if ($this->circuitBreaker->allowsCall()) {
            $response = $this->routingClient->calculateOptimalCrew($this->payload($incident, $candidates));

            if (is_array($response)) {
                $this->circuitBreaker->recordSuccess();
                $decision = $this->decisionFromResponse($response, $candidates);

                if ($decision !== null) {
                    return $decision;
                }

                $error = 'Respuesta de ruteo inválida o cuadrilla no candidata';
            } else {
                $error = 'Microservicio FastAPI no disponible';
            }

            $this->circuitBreaker->recordFailure();
        } else {
            $error = 'Circuit breaker abierto: degradación controlada activada';
        }

        return $this->geodesicFallback($incident, $candidates, $error);
    }

    private function payload(Incident $incident, Collection $candidates): array
    {
        return [
            'incident_id' => $incident->id,
            'incident_location' => [
                'latitude' => $incident->latitude,
                'longitude' => $incident->longitude,
            ],
            'specialty_required' => $incident->category->specialty_required->value,
            'candidates' => $candidates->map(fn (Crew $crew): array => [
                'crew_id' => $crew->id,
                'latitude' => $crew->latitude,
                'longitude' => $crew->longitude,
                'current_load' => $crew->current_load,
            ])->values()->all(),
        ];
    }

    private function decisionFromResponse(array $response, Collection $candidates): ?CrewAssignmentDecision
    {
        $optimal = $response['optimal_crew'] ?? null;

        if (! is_array($optimal) || ! isset($optimal['crew_id'])) {
            return null;
        }

        $crew = $candidates->first(fn (Crew $candidate): bool => $candidate->id === (int) $optimal['crew_id']);

        if ($crew === null) {
            return null;
        }

        return new CrewAssignmentDecision(
            crew: $crew,
            distanceMeters: (float) ($optimal['distance_meters'] ?? 0),
            durationSeconds: (float) ($optimal['duration_seconds'] ?? 0),
            source: 'fastapi_osrm',
            routeSummary: isset($optimal['route_summary']) ? (string) $optimal['route_summary'] : null,
            polyline: isset($optimal['polyline']) ? (string) $optimal['polyline'] : null,
            bridgeCrossingRequired: (bool) ($optimal['bridge_crossing_required'] ?? false),
        );
    }

    private function geodesicFallback(Incident $incident, Collection $candidates, string $error): CrewAssignmentDecision
    {
        $best = $candidates
            ->sortBy(fn (Crew $crew): float => $this->spatialService->distanceMeters(
                $incident->latitude,
                $incident->longitude,
                $crew->latitude,
                $crew->longitude,
            ))
            ->first();

        $distance = $this->spatialService->distanceMeters(
            $incident->latitude,
            $incident->longitude,
            $best->latitude,
            $best->longitude,
        );

        return new CrewAssignmentDecision(
            crew: $best,
            distanceMeters: $distance,
            durationSeconds: $distance / 9.72,
            source: 'postgis_geodesic_fallback',
            routeSummary: 'Asignación geodésica de contingencia',
            error: $error,
        );
    }
}
