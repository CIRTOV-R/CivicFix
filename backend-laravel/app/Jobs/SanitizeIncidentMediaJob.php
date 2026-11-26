<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\MediaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

final class SanitizeIncidentMediaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(private readonly Media $media) {}

    public function handle(MediaService $mediaService): void
    {
        $mediaService->sanitize($this->media->fresh());
    }

    public function failed(Throwable $exception): void
    {
        logger()->error('SanitizeIncidentMediaJob failed', [
            'media_id' => $this->media->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
