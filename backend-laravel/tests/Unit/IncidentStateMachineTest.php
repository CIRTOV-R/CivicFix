<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\IncidentStatus;
use App\Models\Incident;
use App\Services\IncidentStateMachine;
use DomainException;
use Tests\TestCase;

final class IncidentStateMachineTest extends TestCase
{
    public function test_allows_canonical_transitions(): void
    {
        $machine = app(IncidentStateMachine::class);

        $this->assertTrue($machine->canTransition(IncidentStatus::REPORTADO, IncidentStatus::ASIGNADO));
        $this->assertTrue($machine->canTransition(IncidentStatus::ASIGNADO, IncidentStatus::EN_PROCESO));
        $this->assertTrue($machine->canTransition(IncidentStatus::EN_PROCESO, IncidentStatus::RESUELTO));
        $this->assertTrue($machine->canTransition(IncidentStatus::REPORTADO, IncidentStatus::RECHAZADO));
    }

    public function test_rejects_non_canonical_transitions(): void
    {
        $machine = app(IncidentStateMachine::class);

        $this->assertFalse($machine->canTransition(IncidentStatus::RESUELTO, IncidentStatus::EN_PROCESO));
        $this->assertFalse($machine->canTransition(IncidentStatus::RECHAZADO, IncidentStatus::ASIGNADO));
    }

    public function test_transition_updates_incident_status(): void
    {
        $incident = Incident::factory()->create([
            'status' => IncidentStatus::REPORTADO,
        ]);

        $updated = app(IncidentStateMachine::class)->transition($incident, IncidentStatus::ASIGNADO);

        $this->assertSame(IncidentStatus::ASIGNADO, $updated->status);
        $this->assertDatabaseHas('incidents', [
            'id' => $incident->id,
            'status' => IncidentStatus::ASIGNADO->value,
        ]);
    }

    public function test_assert_transition_throws_domain_exception(): void
    {
        $this->expectException(DomainException::class);

        app(IncidentStateMachine::class)->assertTransition(
            IncidentStatus::RESUELTO,
            IncidentStatus::REPORTADO,
        );
    }
}
