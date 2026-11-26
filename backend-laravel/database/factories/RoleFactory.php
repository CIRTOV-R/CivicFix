<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RoleName;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

final class RoleFactory extends Factory
{
    protected $model = Role::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->lexify('ROLE_????'),
            'display_name' => fake()->words(2, true),
        ];
    }

    public function named(RoleName $role): static
    {
        return $this->state(fn (): array => [
            'name' => $role->value,
            'display_name' => ucfirst(strtolower($role->value)),
        ]);
    }
}
