<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CrewSpecialty;
use App\Enums\IncidentPriority;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'specialty_required',
        'default_priority',
        'sla_resolution_hours',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'specialty_required' => CrewSpecialty::class,
            'default_priority' => IncidentPriority::class,
            'sla_resolution_hours' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }
}
