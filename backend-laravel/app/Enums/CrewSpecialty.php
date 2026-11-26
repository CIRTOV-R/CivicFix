<?php

declare(strict_types=1);

namespace App\Enums;

enum CrewSpecialty: string
{
    case VIALIDAD = 'VIALIDAD';
    case ALUMBRADO = 'ALUMBRADO';
    case AGUAS = 'AGUAS';
    case ASEO = 'ASEO';
    case OTROS = 'OTROS';

    public function label(): string
    {
        return match ($this) {
            self::VIALIDAD => 'Vialidad',
            self::ALUMBRADO => 'Alumbrado público',
            self::AGUAS => 'Aguas',
            self::ASEO => 'Aseo urbano',
            self::OTROS => 'Otros servicios',
        };
    }
}
