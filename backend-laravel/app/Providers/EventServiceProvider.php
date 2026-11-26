<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\IncidentAssignedEvent;
use App\Events\IncidentEscalatedEvent;
use App\Events\IncidentRejectedEvent;
use App\Events\IncidentResolvedEvent;
use App\Listeners\NotifyCitizenOfRejection;
use App\Listeners\NotifyCitizenOfResolution;
use App\Listeners\NotifyCrewOfAssignment;
use App\Listeners\NotifySupervisorsOfEscalation;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

final class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        IncidentAssignedEvent::class => [NotifyCrewOfAssignment::class],
        IncidentEscalatedEvent::class => [NotifySupervisorsOfEscalation::class],
        IncidentResolvedEvent::class => [NotifyCitizenOfResolution::class],
        IncidentRejectedEvent::class => [NotifyCitizenOfRejection::class],
    ];
}
