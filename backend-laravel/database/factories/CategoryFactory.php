<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CrewSpecialty;
use App\Enums\IncidentPriority;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

final class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'specialty_required' => CrewSpecialty::VIALIDAD,
            'default_priority' => IncidentPriority::MEDIA,
            'sla_resolution_hours' => 72,
            'is_active' => true,
        ];
    }

    public function specialty(CrewSpecialty $specialty): static
    {
        return $this->state(fn (): array => ['specialty_required' => $specialty]);
    }
}
