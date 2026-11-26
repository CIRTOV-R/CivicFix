<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

final class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [RoleName::CIUDADANO, 'ciudadano@civicfix.local', 'Ciudadano Demo'],
            [RoleName::CUADRILLA, 'cuadrilla@civicfix.local', 'Brigadista Demo'],
            [RoleName::SUPERVISOR, 'supervisor@civicfix.local', 'Supervisor Demo'],
            [RoleName::ADMINISTRADOR, 'admin@civicfix.local', 'Administrador Demo'],
        ];

        foreach ($users as [$role, $email, $name]) {
            User::firstOrCreate(
                ['email' => $email],
                [
                    'role_id' => RoleSeeder::roleId($role),
                    'name' => $name,
                    'password_hash' => Hash::make('civicfix123'),
                    'email_verified_at' => now(),
                    'phone' => '+58 414 0000000',
                    'is_active' => true,
                ],
            );
        }
    }
}
