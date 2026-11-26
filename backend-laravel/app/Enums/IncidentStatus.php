<?php

declare(strict_types=1);

namespace App\Enums;

enum IncidentStatus: string
{
    case REPORTADO = 'REPORTADO';
    case ASIGNADO = 'ASIGNADO';
    case EN_PROCESO = 'EN_PROCESO';
    case RESUELTO = 'RESUELTO';
    case RECHAZADO = 'RECHAZADO';

    public function label(): string
    {
        return match ($this) {
            self::REPORTADO => 'Reportado',
            self::ASIGNADO => 'Asignado',
            self::EN_PROCESO => 'En proceso',
            self::RESUELTO => 'Resuelto',
            self::RECHAZADO => 'Rechazado',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::RESUELTO, self::RECHAZADO], true);
    }
}
