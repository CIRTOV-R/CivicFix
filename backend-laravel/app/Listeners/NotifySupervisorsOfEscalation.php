<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\IncidentEscalatedEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

final class NotifySupervisorsOfEscalation implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(IncidentEscalatedEvent $event): void
    {
        logger()->warning('Incidencia escalada por SLA', [
            'incident_id' => $event->incident->id,
            'priority' => $event->incident->priority->value,
            'escalated_at' => $event->incident->escalated_at?->toIso8601String(),
        ]);
    }
}
