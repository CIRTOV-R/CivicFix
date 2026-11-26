<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\IncidentStatus;
use App\Models\Category;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Database\Seeder;

final class DemoIncidentSeeder extends Seeder
{
    public function run(): void
    {
        $citizenId = User::where('email', 'ciudadano@civicfix.local')->value('id');
        $category = Category::where('name', 'Bache vial')->first();

        if ($citizenId === null || $category === null) {
            return;
        }

        $incidents = [
            [
                'uuid' => '11111111-1111-4111-8111-111111111111',
                'idempotency_key' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
                'title' => 'Bache crítico en Redoma de Chilemex',
                'status' => IncidentStatus::REPORTADO,
                'latitude' => 8.296542,
                'longitude' => -62.741285,
            ],
            [
                'uuid' => '22222222-2222-4222-8222-222222222222',
                'idempotency_key' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
                'title' => 'Hundimiento en Paseo Caroní',
                'status' => IncidentStatus::ASIGNADO,
                'latitude' => 8.270000,
                'longitude' => -62.750000,
            ],
            [
                'uuid' => '33333333-3333-4333-8333-333333333333',
                'idempotency_key' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
                'title' => 'Fallo de vialidad en Zona Industrial Unare',
                'status' => IncidentStatus::EN_PROCESO,
                'latitude' => 8.282000,
                'longitude' => -62.764000,
            ],
        ];

        foreach ($incidents as $incident) {
            Incident::firstOrCreate(
                ['idempotency_key' => $incident['idempotency_key']],
                [
                    'uuid' => $incident['uuid'],
                    'citizen_id' => $citizenId,
                    'category_id' => $category->id,
                    'title' => $incident['title'],
                    'description' => 'Reporte de demostración cargado para la defensiva del sistema.',
                    'address_reference' => 'Ciudad Guayana, Municipio Caroní',
                    'priority' => 'ALTA',
                    'status' => $incident['status'],
                    'latitude' => $incident['latitude'],
                    'longitude' => $incident['longitude'],
                    'sla_due_at' => now()->addHours((int) $category->sla_resolution_hours),
                ],
            );
        }
    }
}
