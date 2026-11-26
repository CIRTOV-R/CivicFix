<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

trait HasGeographicLocation
{
    protected static function bootHasGeographicLocation(): void
    {
        static::saving(function (Model $model): void {
            if (DB::connection()->getDriverName() !== 'pgsql') {
                return;
            }

            $latitude = (float) $model->getAttribute('latitude');
            $longitude = (float) $model->getAttribute('longitude');

            $model->setAttribute(
                'location',
                DB::raw(sprintf('ST_SetSRID(ST_MakePoint(%.8f, %.8f), 4326)::geography', $longitude, $latitude)),
            );
        });
    }
}
