<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\IncidentAssignedEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

final class NotifyCrewOfAssignment implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(IncidentAssignedEvent $event): void
    {
        logger()->info('Orden de servicio notificada a cuadrilla', [
            'incident_id' => $event->incident->id,
            'crew_id' => $event->crew->id,
            'eta_minutes' => $event->assignment['eta_minutes'] ?? null,
        ]);
    }
}
