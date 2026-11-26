<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\IncidentPriority;
use App\Enums\IncidentStatus;
use App\Events\IncidentEscalatedEvent;
use App\Models\Incident;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

final class SlaEscalationTest extends TestCase
{
    public function test_old_high_priority_report_is_escalated_to_critical(): void
    {
        Event::fake([IncidentEscalatedEvent::class]);

        $incident = Incident::factory()->create([
            'status' => IncidentStatus::REPORTADO,
            'priority' => IncidentPriority::ALTA,
            'created_at' => now()->subHours(73),
            'sla_due_at' => now()->subHour(),
        ]);

        $this->artisan('civicfix:check-sla')
            ->expectsOutput('Incidencias escaladas: 1')
            ->assertSuccessful();

        $incident->refresh();
        $this->assertSame(IncidentPriority::CRITICA, $incident->priority);
        $this->assertNotNull($incident->escalated_at);

        $this->assertDatabaseHas('audit_logs', [
            'target_table' => 'incidents',
            'target_id' => $incident->id,
            'action_type' => 'INCIDENT_ESCALATED',
        ]);

        Event::assertDispatched(IncidentEscalatedEvent::class);
    }

    public function test_recent_report_is_not_escalated(): void
    {
        $incident = Incident::factory()->create([
            'status' => IncidentStatus::REPORTADO,
            'priority' => IncidentPriority::ALTA,
            'created_at' => now()->subHours(2),
        ]);

        $this->artisan('civicfix:check-sla')
            ->expectsOutput('Incidencias escaladas: 0')
            ->assertSuccessful();

        $this->assertSame(IncidentPriority::ALTA, $incident->refresh()->priority);
        $this->assertNull($incident->escalated_at);
    }
}
