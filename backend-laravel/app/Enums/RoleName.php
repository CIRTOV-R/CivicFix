<?php

declare(strict_types=1);

namespace App\Enums;

enum RoleName: string
{
    case CIUDADANO = 'CIUDADANO';
    case CUADRILLA = 'CUADRILLA';
    case SUPERVISOR = 'SUPERVISOR';
    case ADMINISTRADOR = 'ADMINISTRADOR';

    public function isStaff(): bool
    {
        return $this !== self::CIUDADANO;
    }

    public function canManage(): bool
    {
        return in_array($this, [self::SUPERVISOR, self::ADMINISTRADOR], true);
    }
}
