<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Crew;
use App\Models\Incident;
use App\Models\Parish;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class SpatialService
{
    public function distanceMeters(float $latitudeA, float $longitudeA, float $latitudeB, float $longitudeB): float
    {
        $earthRadius = 6371008.8;
        $latA = deg2rad($latitudeA);
        $latB = deg2rad($latitudeB);
        $deltaLat = deg2rad($latitudeB - $latitudeA);
        $deltaLon = deg2rad($longitudeB - $longitudeA);

        $haversine = sin($deltaLat / 2) ** 2
            + cos($latA) * cos($latB) * sin($deltaLon / 2) ** 2;

        return 2 * $earthRadius * asin(min(1.0, sqrt($haversine)));
    }

    public function candidatesWithinRadius(Incident $incident, int $radiusMeters): Collection
    {
        $incident->loadMissing('category');
        $specialty = $incident->category->specialty_required;

        $query = Crew::query()
            ->active()
            ->available()
            ->withSpecialty($specialty);

        if (DB::connection()->getDriverName() === 'pgsql') {
            return $query
                ->whereRaw(
                    'ST_DWithin(location, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)',
                    [$incident->longitude, $incident->latitude, $radiusMeters],
                )
                ->orderByRaw(
                    'ST_DistanceSphere(location, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography) asc',
                    [$incident->longitude, $incident->latitude],
                )
                ->get();
        }

        return $query->get()
            ->filter(fn (Crew $crew): bool => $this->distanceMeters(
                $incident->latitude,
                $incident->longitude,
                $crew->latitude,
                $crew->longitude,
            ) <= $radiusMeters)
            ->sortBy(fn (Crew $crew): float => $this->distanceMeters(
                $incident->latitude,
                $incident->longitude,
                $crew->latitude,
                $crew->longitude,
            ))
            ->values();
    }

    public function isWithinRadius(
        float $latitudeA,
        float $longitudeA,
        float $latitudeB,
        float $longitudeB,
        int $radiusMeters,
    ): bool {
        if (DB::connection()->getDriverName() === 'pgsql') {
            $distance = DB::scalar(
                'SELECT ST_DistanceSphere(ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography)',
                [$longitudeA, $latitudeA, $longitudeB, $latitudeB],
            );

            return (float) $distance <= $radiusMeters;
        }

        return $this->distanceMeters($latitudeA, $longitudeA, $latitudeB, $longitudeB) <= $radiusMeters;
    }

    public function nearestParish(float $latitude, float $longitude): ?Parish
    {
        return Parish::query()
            ->get()
            ->sortBy(fn (Parish $parish): float => $this->distanceMeters(
                $latitude,
                $longitude,
                $parish->center_latitude,
                $parish->center_longitude,
            ))
            ->first();
    }
}
