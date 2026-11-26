<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Services\MediaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class MediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Media $media */
        $media = $this->resource;

        return [
            'id' => $media->id,
            'collection_name' => $media->collection_name,
            'file_name' => $media->file_name,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
            'url' => app(MediaService::class)->url($media),
            'exif_removed' => (bool) ($media->custom_properties['exif_removed'] ?? false),
            'created_at' => $media->created_at?->toIso8601String(),
        ];
    }
}
