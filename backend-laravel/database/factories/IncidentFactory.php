<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\IncidentStatus;
use App\Models\Category;
use App\Models\Crew;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

final class IncidentFactory extends Factory
{
    protected $model = Incident::class;

    public function definition(): array
    {
        return [
            'uuid' => fake()->uuid(),
            'idempotency_key' => fake()->unique()->uuid(),
            'citizen_id' => User::factory(),
            'crew_id' => null,
            'category_id' => Category::factory(),
            'parish_id' => null,
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'address_reference' => fake()->address(),
            'priority' => 'MEDIA',
            'status' => IncidentStatus::REPORTADO,
            'rejection_reason' => null,
            'latitude' => 8.296542,
            'longitude' => -62.741285,
            'sla_due_at' => now()->addHours(72),
            'escalated_at' => null,
            'resolved_at' => null,
        ];
    }

    public function withStatus(IncidentStatus $status): static
    {
        return $this->state(fn (): array => ['status' => $status]);
    }

    public function assigned(): static
    {
        return $this->state(fn (): array => ['status' => IncidentStatus::ASIGNADO]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (): array => ['status' => IncidentStatus::EN_PROCESO]);
    }

    public function forCrew(Crew $crew): static
    {
        return $this->state(fn (): array => ['crew_id' => $crew->id]);
    }
}
