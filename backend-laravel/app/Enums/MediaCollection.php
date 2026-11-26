<?php

declare(strict_types=1);

namespace App\Enums;

enum MediaCollection: string
{
    case BEFORE_REPAIR = 'before_repair';
    case AFTER_REPAIR = 'after_repair';

    public function label(): string
    {
        return match ($this) {
            self::BEFORE_REPAIR => 'Evidencia inicial',
            self::AFTER_REPAIR => 'Evidencia posterior',
        };
    }
}
