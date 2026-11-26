<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Parish;
use Illuminate\Database\Seeder;

final class ParishSeeder extends Seeder
{
    public function run(): void
    {
        $parishes = [
            ['Unare', 'UNARE', 8.282000, -62.764000],
            ['Universidad', 'UNIVERSIDAD', 8.296542, -62.741285],
            ['Cachamay', 'CACHAMAY', 8.278000, -62.738000],
            ['Simón Bolívar', 'SIMON_BOLIVAR', 8.312450, -62.685410],
            ['Dalla Costa', 'DALLA_COSTA', 8.306000, -62.670000],
        ];

        foreach ($parishes as [$name, $code, $latitude, $longitude]) {
            Parish::firstOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'center_latitude' => $latitude,
                    'center_longitude' => $longitude,
                ],
            );
        }
    }
}
