<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'specialty_required' => $this->specialty_required?->value,
            'default_priority' => $this->default_priority?->value,
            'sla_resolution_hours' => $this->sla_resolution_hours,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
