<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\IncidentPriority;
use App\Enums\IncidentStatus;
use App\Enums\MediaCollection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

final class Incident extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, SoftDeletes;

    protected $fillable = [
        'uuid',
        'idempotency_key',
        'citizen_id',
        'crew_id',
        'category_id',
        'parish_id',
        'title',
        'description',
        'address_reference',
        'priority',
        'status',
        'rejection_reason',
        'latitude',
        'longitude',
        'sla_due_at',
        'escalated_at',
        'resolved_at',
    ];

    protected $hidden = [
        'location',
    ];

    protected function casts(): array
    {
        return [
            'priority' => IncidentPriority::class,
            'status' => IncidentStatus::class,
            'latitude' => 'float',
            'longitude' => 'float',
            'sla_due_at' => 'immutable_datetime',
            'escalated_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
        ];
    }

    public function citizen(): BelongsTo
    {
        return $this->belongsTo(User::class, 'citizen_id');
    }

    public function crew(): BelongsTo
    {
        return $this->belongsTo(Crew::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function parish(): BelongsTo
    {
        return $this->belongsTo(Parish::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(MediaCollection::BEFORE_REPAIR->value);
        $this->addMediaCollection(MediaCollection::AFTER_REPAIR->value);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            IncidentStatus::RESUELTO->value,
            IncidentStatus::RECHAZADO->value,
        ]);
    }

    public function scopeByStatus(Builder $query, IncidentStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }
}
