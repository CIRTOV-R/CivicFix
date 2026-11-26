<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RoleName;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

final class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'role_id' => Role::firstOrCreate(
                ['name' => RoleName::CIUDADANO->value],
                ['display_name' => 'Ciudadano'],
            )->id,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password_hash' => Hash::make('password'),
            'phone' => fake()->phoneNumber(),
            'is_active' => true,
        ];
    }

    public function withRole(RoleName $role): static
    {
        return $this->state(fn (): array => [
            'role_id' => Role::firstOrCreate(
                ['name' => $role->value],
                ['display_name' => ucfirst(strtolower($role->value))],
            )->id,
        ]);
    }
}
