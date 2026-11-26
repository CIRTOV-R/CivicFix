<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class IncidentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'title' => $this->title,
            'description' => $this->description,
            'address_reference' => $this->address_reference,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'priority' => $this->priority?->value,
            'priority_label' => $this->priority?->label(),
            'latitude' => (float) $this->latitude,
            'longitude' => (float) $this->longitude,
            'rejection_reason' => $this->rejection_reason,
            'sla_due_at' => $this->sla_due_at?->toIso8601String(),
            'escalated_at' => $this->escalated_at?->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'crew' => new CrewResource($this->whenLoaded('crew')),
            'citizen' => new UserResource($this->whenLoaded('citizen')),
            'parish' => $this->whenLoaded('parish', fn (): ?string => $this->parish?->name),
            'media' => MediaResource::collection($this->whenLoaded('media')),
            'timeline' => AuditLogResource::collection($this->whenLoaded('auditLogs')),
        ];
    }
}
