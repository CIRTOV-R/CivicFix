<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\IncidentStatus;
use App\Models\Incident;
use DomainException;

final class IncidentStateMachine
{
    private const TRANSITIONS = [
        IncidentStatus::REPORTADO->value => [
            IncidentStatus::ASIGNADO,
            IncidentStatus::RECHAZADO,
        ],
        IncidentStatus::ASIGNADO->value => [
            IncidentStatus::EN_PROCESO,
            IncidentStatus::RECHAZADO,
        ],
        IncidentStatus::EN_PROCESO->value => [
            IncidentStatus::RESUELTO,
            IncidentStatus::RECHAZADO,
        ],
        IncidentStatus::RESUELTO->value => [],
        IncidentStatus::RECHAZADO->value => [],
    ];

    public function canTransition(IncidentStatus $from, IncidentStatus $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from->value] ?? [], true);
    }

    public function assertTransition(IncidentStatus $from, IncidentStatus $to): void
    {
        if (! $this->canTransition($from, $to)) {
            throw new DomainException(sprintf(
                'Transición de estado no permitida: %s → %s',
                $from->value,
                $to->value,
            ));
        }
    }

    public function transition(Incident $incident, IncidentStatus $to, array $attributes = []): Incident
    {
        $this->assertTransition($incident->status, $to);

        $incident->update(array_merge($attributes, ['status' => $to]));

        return $incident->refresh();
    }
}
