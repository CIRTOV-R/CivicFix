<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Crew;

final readonly class CrewAssignmentDecision
{
    public function __construct(
        public Crew $crew,
        public float $distanceMeters,
        public float $durationSeconds,
        public string $source,
        public ?string $routeSummary = null,
        public ?string $polyline = null,
        public bool $bridgeCrossingRequired = false,
        public ?string $error = null,
    ) {}

    public function toArray(): array
    {
        return [
            'crew_id' => $this->crew->id,
            'crew_name' => $this->crew->name,
            'distance_meters' => round($this->distanceMeters, 2),
            'duration_seconds' => round($this->durationSeconds, 2),
            'distance_km' => round($this->distanceMeters / 1000, 2),
            'eta_minutes' => round($this->durationSeconds / 60, 1),
            'source' => $this->source,
            'route_summary' => $this->routeSummary,
            'polyline' => $this->polyline,
            'bridge_crossing_required' => $this->bridgeCrossingRequired,
            'error' => $this->error,
        ];
    }
}
