<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CrewSpecialty;
use App\Models\Concerns\HasGeographicLocation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Crew extends Model
{
    use HasFactory, HasGeographicLocation;

    protected $fillable = [
        'name',
        'specialty',
        'supervisor_id',
        'parish_id',
        'contact_phone',
        'is_active',
        'current_capacity',
        'current_load',
        'latitude',
        'longitude',
        'last_location_update',
    ];

    protected $hidden = [
        'location',
    ];

    protected function casts(): array
    {
        return [
            'specialty' => CrewSpecialty::class,
            'is_active' => 'boolean',
            'current_capacity' => 'integer',
            'current_load' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
            'last_location_update' => 'immutable_datetime',
        ];
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function parish(): BelongsTo
    {
        return $this->belongsTo(Parish::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeWithSpecialty(Builder $query, CrewSpecialty|string $specialty): Builder
    {
        $value = $specialty instanceof CrewSpecialty ? $specialty->value : $specialty;

        return $query->where('specialty', $value);
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->whereColumn('current_load', '<', 'current_capacity');
    }
}
