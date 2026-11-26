<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RoleName;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

final class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'role_id',
        'crew_id',
        'name',
        'email',
        'password_hash',
        'phone',
        'is_active',
        'email_verified_at',
    ];

    protected $hidden = [
        'password_hash',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'immutable_datetime',
            'password_hash' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function getAuthPassword(): string
    {
        return (string) $this->password_hash;
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function crew(): BelongsTo
    {
        return $this->belongsTo(Crew::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class, 'citizen_id');
    }

    public function supervisedCrews(): HasMany
    {
        return $this->hasMany(Crew::class, 'supervisor_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    protected function roleName(): Attribute
    {
        return Attribute::get(fn (): ?RoleName => $this->role ? RoleName::tryFrom($this->role->name) : null);
    }

    public function hasRole(RoleName|string $role): bool
    {
        $value = $role instanceof RoleName ? $role->value : $role;

        return $this->role?->name === $value;
    }

    public function isStaff(): bool
    {
        return $this->roleName?->isStaff() ?? false;
    }

    public function canManage(): bool
    {
        return $this->roleName?->canManage() ?? false;
    }
}
