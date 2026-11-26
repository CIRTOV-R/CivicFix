<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Incidents\AssignOptimalCrewAction;
use App\Enums\IncidentStatus;
use App\Models\Crew;
use App\Models\Incident;
use App\Services\AssignmentService;
use App\Services\SpatialService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class AssignmentTest extends TestCase
{
    public function test_service_uses_fastapi_decision_with_internal_token(): void
    {
        $incident = Incident::factory()->create([
            'latitude' => 8.2950,
            'longitude' => -62.7420,
        ]);
        $crew = Crew::factory()->at(8.2970, -62.7400)->create();

        Http::fake([
            '*' => Http::response([
                'status' => 'success',
                'incident_id' => $incident->id,
                'optimal_crew' => [
                    'crew_id' => $crew->id,
                    'distance_meters' => 342.5,
                    'duration_seconds' => 480.0,
                    'distance_km' => 0.34,
                    'eta_minutes' => 8.0,
                    'route_summary' => 'Av. Guayana → C. Unare',
                    'bridge_crossing_required' => false,
                    'polyline' => 'testpolyline',
                ],
                'evaluated_candidates_count' => 1,
            ], 200),
        ]);

        $decision = app(AssignmentService::class)->resolveOptimalCrew($incident);

        $this->assertSame($crew->id, $decision->crew->id);
        $this->assertSame('fastapi_osrm', $decision->source);
        $this->assertSame(342.5, $decision->distanceMeters);

        Http::assertSent(function (Request $request) use ($incident, $crew): bool {
            return $request->url() === config('services.routing.url').'/api/v1/routing/calculate-optimal-crew'
                && $request->hasHeader('X-Internal-Token', config('services.routing.token'))
                && $request['incident_id'] === $incident->id
                && $request['candidates'][0]['crew_id'] === $crew->id;
        });
    }

    public function test_action_falls_back_to_geodesic_assignment_and_audits_failure(): void
    {
        Http::fake(['*' => Http::response('internal error', 500)]);

        $incident = Incident::factory()->create([
            'latitude' => 8.2950,
            'longitude' => -62.7420,
            'priority' => 'ALTA',
        ]);
        $crew = Crew::factory()->at(8.2952, -62.7422)->create();

        $resolvedCrew = app(AssignOptimalCrewAction::class)->execute($incident);

        $this->assertSame($crew->id, $resolvedCrew->id);
        $this->assertSame(IncidentStatus::ASIGNADO, $incident->refresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'target_table' => 'incidents',
            'target_id' => $incident->id,
            'action_type' => 'ROUTING_FALLBACK',
        ]);
    }

    public function test_crew_outside_prefilter_radius_is_not_candidate(): void
    {
        $incident = Incident::factory()->create([
            'latitude' => 8.2950,
            'longitude' => -62.7420,
        ]);

        Crew::factory()->at(9.5000, -64.0000)->create();
        $nearCrew = Crew::factory()->at(8.2960, -62.7430)->create();

        $candidates = app(SpatialService::class)->candidatesWithinRadius(
            $incident,
            (int) config('civicfix.prefilter_radius_meters'),
        );

        $this->assertCount(1, $candidates);
        $this->assertSame($nearCrew->id, $candidates->first()->id);
    }
}
