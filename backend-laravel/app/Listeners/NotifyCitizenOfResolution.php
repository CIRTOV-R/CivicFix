<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\IncidentResolvedEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

final class NotifyCitizenOfResolution implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(IncidentResolvedEvent $event): void
    {
        logger()->info('Incidencia resuelta notificada al ciudadano', [
            'incident_id' => $event->incident->id,
            'citizen_id' => $event->incident->citizen_id,
        ]);
    }
}
