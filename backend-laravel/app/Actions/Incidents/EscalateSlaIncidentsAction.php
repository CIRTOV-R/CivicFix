<?php

declare(strict_types=1);

namespace App\Actions\Incidents;

use App\Enums\AuditAction;
use App\Enums\IncidentPriority;
use App\Enums\IncidentStatus;
use App\Events\IncidentEscalatedEvent;
use App\Models\Incident;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;

final class EscalateSlaIncidentsAction
{
    public function __construct(private readonly AuditService $auditService) {}

    public function execute(): int
    {
        $hours = (int) config('civicfix.sla_hours', 72);
        $count = 0;

        Incident::query()
            ->where('status', IncidentStatus::REPORTADO->value)
            ->where('priority', IncidentPriority::ALTA->value)
            ->where('created_at', '<=', now()->subHours($hours))
            ->whereNull('escalated_at')
            ->chunkById(100, function ($incidents) use (&$count): void {
                foreach ($incidents as $incident) {
                    DB::transaction(function () use ($incident): void {
                        $incident->update([
                            'priority' => IncidentPriority::CRITICA,
                            'escalated_at' => now(),
                        ]);

                        $this->auditService->log(AuditAction::INCIDENT_ESCALATED, $incident, [
                            'priority' => IncidentPriority::ALTA->value,
                            'escalated_at' => null,
                        ], [
                            'priority' => IncidentPriority::CRITICA->value,
                            'escalated_at' => now()->toIso8601String(),
                            'sla_hours' => (int) config('civicfix.sla_hours', 72),
                        ]);
                    });

                    event(new IncidentEscalatedEvent($incident->refresh()));
                    $count++;
                }
            });

        return $count;
    }
}
