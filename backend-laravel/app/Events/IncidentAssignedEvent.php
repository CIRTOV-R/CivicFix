<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Crew;
use App\Models\Incident;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class IncidentAssignedEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Incident $incident,
        public readonly Crew $crew,
        public readonly array $assignment = [],
    ) {}
}
