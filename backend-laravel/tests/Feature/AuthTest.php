<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class AuthTest extends TestCase
{
    public function test_citizen_can_register_and_receive_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Ana Pérez',
            'email' => 'ana@example.com',
            'password' => 'civicfix123',
            'password_confirmation' => 'civicfix123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.role', RoleName::CIUDADANO->value)
            ->assertJsonStructure(['token', 'data' => ['user' => ['id', 'name', 'email'], 'token']]);

        $this->assertDatabaseHas('users', ['email' => 'ana@example.com']);
    }

    public function test_user_can_login_and_read_profile(): void
    {
        $user = User::factory()->withRole(RoleName::SUPERVISOR)->create([
            'email' => 'sup@example.com',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'sup@example.com',
            'password' => 'password',
        ])->assertOk()->assertJsonPath('data.user.role', RoleName::SUPERVISOR->value);

        Sanctum::actingAs($user);
        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.role', RoleName::SUPERVISOR->value);
    }

    public function test_guest_cannot_read_profile(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }
}
