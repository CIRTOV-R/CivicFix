<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CrewSpecialty;
use App\Models\Crew;
use Illuminate\Database\Eloquent\Factories\Factory;

final class CrewFactory extends Factory
{
    protected $model = Crew::class;

    public function definition(): array
    {
        return [
            'name' => 'Cuadrilla '.fake()->unique()->lexify('???'),
            'specialty' => CrewSpecialty::VIALIDAD,
            'supervisor_id' => null,
            'parish_id' => null,
            'contact_phone' => fake()->phoneNumber(),
            'is_active' => true,
            'current_capacity' => 5,
            'current_load' => 0,
            'latitude' => 8.296542,
            'longitude' => -62.741285,
            'last_location_update' => now(),
        ];
    }

    public function at(float $latitude, float $longitude): static
    {
        return $this->state(fn (): array => [
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);
    }

    public function specialty(CrewSpecialty $specialty): static
    {
        return $this->state(fn (): array => ['specialty' => $specialty]);
    }
}
