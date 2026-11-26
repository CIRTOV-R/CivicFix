<?php

declare(strict_types=1);

namespace App\Enums;

enum IncidentPriority: string
{
    case BAJA = 'BAJA';
    case MEDIA = 'MEDIA';
    case ALTA = 'ALTA';
    case CRITICA = 'CRITICA';

    public function label(): string
    {
        return match ($this) {
            self::BAJA => 'Baja',
            self::MEDIA => 'Media',
            self::ALTA => 'Alta',
            self::CRITICA => 'Crítica',
        };
    }

    public function weight(): int
    {
        return match ($this) {
            self::BAJA => 1,
            self::MEDIA => 2,
            self::ALTA => 3,
            self::CRITICA => 4,
        };
    }
}
