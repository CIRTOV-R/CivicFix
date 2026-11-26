<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MediaCollection;
use App\Jobs\SanitizeIncidentMediaJob;
use App\Models\Incident;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class MediaService
{
    public function attach(Incident $incident, ?UploadedFile $file, MediaCollection $collection): ?Media
    {
        if ($file === null) {
            return null;
        }

        $media = $incident->addMedia($file)->toMediaCollection($collection->value);

        SanitizeIncidentMediaJob::dispatch($media)->afterCommit();

        return $media;
    }

    public function url(Media $media, int $minutes = 10): string
    {
        $path = $media->getPathRelativeToRoot();

        try {
            $url = Storage::disk($media->disk)->temporaryUrl($path, now()->addMinutes($minutes));
            $endpoint = (string) config('filesystems.disks.s3.endpoint');
            $publicEndpoint = (string) config('filesystems.disks.s3.url');

            if ($endpoint !== '' && $publicEndpoint !== '' && str_starts_with($url, $endpoint)) {
                return rtrim($publicEndpoint, '/').substr($url, strlen(rtrim($endpoint, '/')));
            }

            return $url;
        } catch (RuntimeException) {
            return $media->getUrl();
        }
    }

    public function sanitize(Media $media): void
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagewebp')) {
            throw new RuntimeException('GD con soporte WebP es requerido para sanear evidencias.');
        }

        $disk = Storage::disk($media->disk);
        $path = $media->getPathRelativeToRoot();
        $contents = $disk->get($path);
        $image = imagecreatefromstring($contents);

        if ($image === false) {
            throw new RuntimeException('La evidencia fotográfica no es una imagen válida.');
        }

        $image = imagescale($image, min(imagesx($image), 1600));
        $temporaryPath = tempnam(sys_get_temp_dir(), 'civicfix').'.webp';
        $maxBytes = (int) config('civicfix.media_max_kb', 350) * 1024;

        foreach ([82, 75, 68, 60, 50, 40] as $quality) {
            imagewebp($image, $temporaryPath, $quality);
            clearstatcache(true, $temporaryPath);

            if (filesize($temporaryPath) <= $maxBytes) {
                break;
            }
        }

        if (filesize($temporaryPath) > $maxBytes) {
            $image = imagescale($image, (int) (imagesx($image) * 0.7));
            imagewebp($image, $temporaryPath, 45);
            clearstatcache(true, $temporaryPath);
        }

        $newPath = preg_replace('/\.[^.]+$/', '', $path).'.webp';
        $disk->put($newPath, file_get_contents($temporaryPath));

        if ($newPath !== $path) {
            $disk->delete($path);
        }

        @unlink($temporaryPath);
        imagedestroy($image);

        $media->forceFill([
            'file_name' => basename($newPath),
            'mime_type' => 'image/webp',
            'size' => $disk->size($newPath),
            'custom_properties' => array_merge($media->custom_properties ?? [], [
                'exif_removed' => true,
                'sanitized_at' => now()->toIso8601String(),
                'max_kb' => (int) config('civicfix.media_max_kb', 350),
            ]),
        ])->save();
    }
}
