<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class CrewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'specialty' => $this->specialty?->value,
            'specialty_label' => $this->specialty?->label(),
            'latitude' => (float) $this->latitude,
            'longitude' => (float) $this->longitude,
            'is_active' => (bool) $this->is_active,
            'current_capacity' => (int) $this->current_capacity,
            'current_load' => (int) $this->current_load,
            'contact_phone' => $this->contact_phone,
            'last_location_update' => $this->last_location_update?->toIso8601String(),
            'parish' => $this->whenLoaded('parish', fn (): ?string => $this->parish?->name),
        ];
    }
}
