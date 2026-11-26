<?php

declare(strict_types=1);

return [
    'prefilter_radius_meters' => (int) env('CIVICFIX_PREFILTER_RADIUS_METERS', 15000),
    'geofence_radius_meters' => (int) env('CIVICFIX_GEOFENCE_RADIUS_METERS', 50),
    'sla_hours' => (int) env('CIVICFIX_SLA_HOURS', 72),
    'media_max_kb' => (int) env('CIVICFIX_MEDIA_MAX_KB', 350),
    'default_city' => 'Ciudad Guayana, Bolívar, Venezuela',
    'caroni_bridges' => [
        'Puente Caroní',
        'Puente Macagua',
        'Puente Angosturita',
    ],
    'priority_order' => [
        'CRITICA' => 4,
        'ALTA' => 3,
        'MEDIA' => 2,
        'BAJA' => 1,
    ],
];
