<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CrewSpecialty;
use App\Enums\RoleName;
use App\Models\Crew;
use App\Models\Parish;
use App\Models\User;
use Illuminate\Database\Seeder;

final class CrewSeeder extends Seeder
{
    public function run(): void
    {
        $supervisorId = User::where('role_id', RoleSeeder::roleId(RoleName::SUPERVISOR))->value('id');
        $unare = Parish::where('code', 'UNARE')->value('id');
        $cachamay = Parish::where('code', 'CACHAMAY')->value('id');
        $universidad = Parish::where('code', 'UNIVERSIDAD')->value('id');

        $crews = [
            ['Cuadrilla A - Vialidad Unare', CrewSpecialty::VIALIDAD, 8.282000, -62.764000, '+58 414 1111111', $unare],
            ['Cuadrilla B - Alumbrado Centro Cívico', CrewSpecialty::ALUMBRADO, 8.278000, -62.738000, '+58 414 2222222', $cachamay],
            ['Cuadrilla C - Aguas Los Olivos', CrewSpecialty::AGUAS, 8.293000, -62.720000, '+58 414 3333333', $universidad],
        ];

        foreach ($crews as [$name, $specialty, $latitude, $longitude, $phone, $parishId]) {
            Crew::firstOrCreate(
                ['name' => $name],
                [
                    'specialty' => $specialty,
                    'supervisor_id' => $supervisorId,
                    'parish_id' => $parishId,
                    'contact_phone' => $phone,
                    'is_active' => true,
                    'current_capacity' => 5,
                    'current_load' => 0,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'last_location_update' => now(),
                ],
            );
        }

        $crewId = Crew::where('name', 'Cuadrilla A - Vialidad Unare')->value('id');
        User::where('email', 'cuadrilla@civicfix.local')->update(['crew_id' => $crewId]);
    }
}
