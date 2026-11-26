<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\Role;
use Illuminate\Database\Seeder;

final class RoleSeeder extends Seeder
{
    public static function roleId(RoleName $role): int
    {
        return (int) Role::where('name', $role->value)->value('id');
    }

    public function run(): void
    {
        $roles = [
            RoleName::CIUDADANO->value => 'Ciudadano reportante',
            RoleName::CUADRILLA->value => 'Brigadista de cuadrilla',
            RoleName::SUPERVISOR->value => 'Supervisor municipal',
            RoleName::ADMINISTRADOR->value => 'Administrador del sistema',
        ];

        foreach ($roles as $name => $displayName) {
            Role::firstOrCreate(
                ['name' => $name],
                ['display_name' => $displayName],
            );
        }
    }
}
