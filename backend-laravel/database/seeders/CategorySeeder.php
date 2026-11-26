<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CrewSpecialty;
use App\Enums\IncidentPriority;
use App\Models\Category;
use Illuminate\Database\Seeder;

final class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['Bache vial', CrewSpecialty::VIALIDAD, IncidentPriority::ALTA, 48],
            ['Fuga de agua', CrewSpecialty::AGUAS, IncidentPriority::ALTA, 24],
            ['Fallo de alumbrado', CrewSpecialty::ALUMBRADO, IncidentPriority::MEDIA, 72],
            ['Obstrucción de vía pública', CrewSpecialty::VIALIDAD, IncidentPriority::CRITICA, 24],
            ['Aseo urbano', CrewSpecialty::ASEO, IncidentPriority::MEDIA, 72],
        ];

        foreach ($categories as [$name, $specialty, $priority, $slaHours]) {
            Category::firstOrCreate(
                ['name' => $name],
                [
                    'specialty_required' => $specialty,
                    'default_priority' => $priority,
                    'sla_resolution_hours' => $slaHours,
                    'is_active' => true,
                ],
            );
        }
    }
}
