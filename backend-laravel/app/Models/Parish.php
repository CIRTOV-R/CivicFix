<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Parish extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'center_latitude',
        'center_longitude',
    ];

    protected function casts(): array
    {
        return [
            'center_latitude' => 'float',
            'center_longitude' => 'float',
        ];
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    public function crews(): HasMany
    {
        return $this->hasMany(Crew::class);
    }
}
