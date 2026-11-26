<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class IdempotencyResponse extends Model
{
    use HasFactory;

    protected $fillable = [
        'idempotency_key',
        'user_id',
        'method',
        'path',
        'status_code',
        'response_body',
    ];

    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'response_body' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
