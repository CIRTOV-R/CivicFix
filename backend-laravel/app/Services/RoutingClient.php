<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class RoutingClient
{
    private const CACHE_TTL_SECONDS = 60;

    public function calculateOptimalCrew(array $payload): ?array
    {
        $cacheKey = 'civicfix:routing:'.hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        try {
            $response = Http::timeout((float) config('services.routing.timeout', 1.5))
                ->acceptJson()
                ->withHeaders([
                    'X-Internal-Token' => (string) config('services.routing.token', ''),
                ])
                ->post($this->endpoint(), $payload);

            if (! $response->successful()) {
                return null;
            }

            $data = $response->json();

            if (! is_array($data) || ! isset($data['optimal_crew']['crew_id'])) {
                return null;
            }

            Cache::put($cacheKey, $data, now()->addSeconds(self::CACHE_TTL_SECONDS));

            return $data;
        } catch (Throwable $exception) {
            Log::warning('FastAPI routing unavailable', [
                'message' => $exception->getMessage(),
                'endpoint' => $this->endpoint(),
            ]);

            return null;
        }
    }

    private function endpoint(): string
    {
        return rtrim((string) config('services.routing.url'), '/').'/api/v1/routing/calculate-optimal-crew';
    }
}
