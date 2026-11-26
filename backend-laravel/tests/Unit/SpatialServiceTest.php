<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Crew;
use App\Models\Incident;
use App\Services\SpatialService;
use Tests\TestCase;

final class SpatialServiceTest extends TestCase
{
    public function test_haversine_distance_for_one_degree_of_latitude(): void
    {
        $distance = app(SpatialService::class)->distanceMeters(8.0, -62.0, 9.0, -62.0);

        $this->assertEqualsWithDelta(111195, $distance, 300);
    }

    public function test_geofence_accepts_nearby_point_and_rejects_far_point(): void
    {
        $service = app(SpatialService::class);

        $this->assertTrue($service->isWithinRadius(8.2950, -62.7420, 8.29501, -62.74201, 50));
        $this->assertFalse($service->isWithinRadius(8.2950, -62.7420, 8.3000, -62.7420, 50));
    }

    public function test_candidate_crews_are_filtered_by_prefilter_radius(): void
    {
        $incident = Incident::factory()->create([
            'latitude' => 8.2950,
            'longitude' => -62.7420,
        ]);

        Crew::factory()->at(9.5, -64.0)->create();
        $nearCrew = Crew::factory()->at(8.2960, -62.7430)->create();

        $candidates = app(SpatialService::class)->candidatesWithinRadius($incident, 15000);

        $this->assertCount(1, $candidates);
        $this->assertSame($nearCrew->id, $candidates->first()->id);
    }
}
