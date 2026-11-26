<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\IncidentRejectedEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

final class NotifyCitizenOfRejection implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(IncidentRejectedEvent $event): void
    {
        logger()->info('Incidencia rechazada notificada al ciudadano', [
            'incident_id' => $event->incident->id,
            'citizen_id' => $event->incident->citizen_id,
            'reason' => $event->reason,
        ]);
    }
}
