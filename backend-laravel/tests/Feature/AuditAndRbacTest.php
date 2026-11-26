<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Incident;
use App\Models\User;
use App\Services\AuditService;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Tests\TestCase;

final class AuditAndRbacTest extends TestCase
{
    public function test_audit_log_is_immutable(): void
    {
        $incident = Incident::factory()->create();
        $log = app(AuditService::class)->log('TEST_ACTION', $incident, [], ['ok' => true]);

        $this->assertDatabaseHas('audit_logs', [
            'id' => $log->id,
            'action_type' => 'TEST_ACTION',
        ]);

        try {
            $log->update(['action_type' => 'TAMPERED']);
            $this->fail('La actualización de auditoría debería haber fallado.');
        } catch (LogicException $exception) {
            $this->assertSame('La bitácora de auditoría es inmutable.', $exception->getMessage());
        }

        try {
            $log->delete();
            $this->fail('La eliminación de auditoría debería haber fallado.');
        } catch (LogicException) {
            $this->assertTrue(true);
        }
    }

    public function test_audit_log_captures_actor_ip_and_values(): void
    {
        $user = User::factory()->withRole(RoleName::ADMINISTRADOR)->create();
        Sanctum::actingAs($user);
        request()->server->set('REMOTE_ADDR', '10.20.30.40');
        request()->headers->set('User-Agent', 'CivicFixTest/1.0');

        $incident = Incident::factory()->create();
        $log = app(AuditService::class)->log(
            'STATE_CHANGED',
            $incident,
            ['status' => 'REPORTADO'],
            ['status' => 'ASIGNADO'],
            $user,
        );

        $this->assertSame($user->id, $log->user_id);
        $this->assertSame(['status' => 'REPORTADO'], $log->old_values);
        $this->assertSame(['status' => 'ASIGNADO'], $log->new_values);
        $this->assertSame('10.20.30.40', $log->ip_address);
        $this->assertSame('CivicFixTest/1.0', $log->user_agent);
    }

    public function test_citizen_cannot_access_staff_or_admin_endpoints(): void
    {
        Sanctum::actingAs(User::factory()->withRole(RoleName::CIUDADANO)->create());

        $this->getJson('/api/v1/dashboard')->assertForbidden();
        $this->getJson('/api/v1/audit-logs')->assertForbidden();
        $this->getJson('/api/v1/crews')->assertForbidden();
    }

    public function test_supervisor_can_access_dashboard_but_not_audit_logs(): void
    {
        Sanctum::actingAs(User::factory()->withRole(RoleName::SUPERVISOR)->create());

        $this->getJson('/api/v1/dashboard')->assertOk();
        $this->getJson('/api/v1/audit-logs')->assertForbidden();
        $this->getJson('/api/v1/crews')->assertOk();
    }

    public function test_admin_can_access_all_staff_endpoints(): void
    {
        Sanctum::actingAs(User::factory()->withRole(RoleName::ADMINISTRADOR)->create());

        $this->getJson('/api/v1/dashboard')->assertOk();
        $this->getJson('/api/v1/audit-logs')->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['total']]);
        $this->getJson('/api/v1/crews')->assertOk();
    }
}
